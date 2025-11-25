<?php

namespace App\Controller;

use App\Entity\Don;
use App\Entity\RendezVous;
use App\Form\DonType;
use App\Repository\DonateurRepository;
use App\Repository\RendezVousRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        DonateurRepository $donateurRepo,
        RendezVousRepository $rdvRepo,
        StockRepository $stockRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMINISTRATEUR');

        // Compter uniquement les donateurs avec ROLE_DONATEUR
        $totalDonateurs = $donateurRepo->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.roles LIKE :role')
            ->setParameter('role', '%ROLE_DONATEUR%')
            ->getQuery()
            ->getSingleScalarResult();

        // Compter les rendez-vous à valider (Effectué sans Don associé)
        $rdvAValider = $rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin('App\Entity\Don', 'd', 'WITH', 'd.rendezVous = r')
            ->where('r.statut = :statut')
            ->andWhere('d.id IS NULL')
            ->setParameter('statut', 'Effectué')
            ->getQuery()
            ->getSingleScalarResult();

        $stats = [
            'totalDonateurs' => $totalDonateurs, 
            'stockCritique' => $stockRepo->count(['niveauAlerte' => 'Critique']),
            'rdvAVALIDER' => $rdvAValider,
        ];

        $stocksCritiques = $stockRepo->findBy(['niveauAlerte' => 'Critique']);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'stocksCritiques' => $stocksCritiques,
        ]);
    }

    #[Route('/admin/don/valider', name: 'admin_don_valider')]
    public function valider(
        Request $request,
        RendezVousRepository $rdvRepo,
        EntityManagerInterface $entityManager
    ): Response {
        
        $this->denyAccessUnlessGranted('ROLE_ADMINISTRATEUR');

        // Récupérer les rendez-vous "Effectué" sans Don associé
        $rendezVous = $rdvRepo->findEffectuesSansDon();

        // DEBUG : Vérifier les rendez-vous "Effectué" et leurs Dons
        // Utiliser les métadonnées Doctrine pour obtenir les vrais noms de tables
        $conn = $entityManager->getConnection();
        $rdvMetadata = $entityManager->getClassMetadata('App\Entity\RendezVous');
        $donMetadata = $entityManager->getClassMetadata('App\Entity\Don');
        $rdvTable = $rdvMetadata->getTableName();
        $donTable = $donMetadata->getTableName();
        $rdvIdColumn = $rdvMetadata->getSingleIdentifierColumnName();
        $donRdvColumn = $donMetadata->getAssociationMapping('rendezVous')['joinColumns'][0]['name'] ?? 'rendez_vous_id';
        
        $sql = "SELECT r.{$rdvIdColumn} as id, r.statut, d.id as don_id 
                FROM {$rdvTable} r 
                LEFT JOIN {$donTable} d ON d.{$donRdvColumn} = r.{$rdvIdColumn} 
                WHERE LOWER(TRIM(r.statut)) IN ('effectué', 'effectue', 'effectuee')";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $rows = $result->fetchAllAssociative();
        
        $effectuesAvecDon = 0;
        $effectuesSansDon = 0;
        $idsSansDon = [];
        
        foreach ($rows as $row) {
            if ($row['don_id'] !== null) {
                $effectuesAvecDon++;
            } else {
                $effectuesSansDon++;
                $idsSansDon[] = $row['id'];
            }
        }
        
        // Afficher les informations de diagnostic détaillées
        $idsAvecDon = [];
        foreach ($rows as $row) {
            if ($row['don_id'] !== null) {
                $idsAvecDon[] = $row['id'];
            }
        }
        
        if (empty($rendezVous)) {
            if ($effectuesSansDon > 0) {
                // Il y a des rendez-vous "Effectué" sans Don mais ils ne sont pas retournés
                $this->addFlash('error', 
                    'PROBLÈME DÉTECTÉ: ' . $effectuesSansDon . ' rendez-vous "Effectué" sans Don trouvés (IDs: ' . 
                    implode(', ', $idsSansDon) . ') mais non retournés par findEffectuesSansDon(). ' .
                    'Vérifiez la méthode dans RendezVousRepository.'
                );
            } else {
                // Tous les rendez-vous "Effectué" ont déjà un Don
                $this->addFlash('info', 
                    'Tous les ' . count($rows) . ' rendez-vous "Effectué" ont déjà été validés. ' .
                    'IDs avec Don: ' . implode(', ', $idsAvecDon) . '. ' .
                    'Aucun rendez-vous en attente de validation.'
                );
            }
        } else {
            // Afficher un message de succès avec le nombre trouvé
            $idsTrouves = array_map(function($rdv) { return $rdv->getId(); }, $rendezVous);
            $this->addFlash('success', 
                count($rendezVous) . ' rendez-vous "Effectué" en attente de validation trouvés (IDs: ' . 
                implode(', ', $idsTrouves) . ').'
            );
        }

        // Créer un tableau pour stocker les formulaires
        $forms = [];

        // Traiter le formulaire soumis si présent
        if ($request->isMethod('POST')) {
            $rdvId = $request->request->get('rendez_vous_id');
            
            if ($rdvId) {
                // Récupérer le rendez-vous directement depuis la base de données
                $rdv = $rdvRepo->find($rdvId);
                
                if (!$rdv) {
                    $this->addFlash('error', 'Rendez-vous non trouvé.');
                } elseif ($rdv->getStatut() !== 'Effectué') {
                    $this->addFlash('error', 'Ce rendez-vous n\'a pas le statut "Effectué".');
                } else {
                    // Vérifier qu'il n'y a pas déjà un Don pour ce rendez-vous
                    $donExistant = $entityManager->getRepository(Don::class)
                        ->findOneBy(['rendezVous' => $rdv]);
                    
                    if ($donExistant) {
                        $this->addFlash('error', 'Un don existe déjà pour ce rendez-vous.');
                    } else {
                    $don = new Don();
                    $don->setRendezVous($rdv);
                    $don->setDonateurId($rdv->getDonateur());
                    $don->setDatedon(new \DateTime());
                        $don->setApte(false); // Valeur par défaut : non apte
                    
                    $form = $this->createForm(DonType::class, $don);
                    $form->handleRequest($request);

                    if ($form->isSubmitted() && $form->isValid()) {
                            // S'assurer que apte n'est pas null (false si non coché)
                            if ($don->isApte() === null) {
                                $don->setApte(false);
                            }
                            
                        // Persister le Don
                        $entityManager->persist($don);
                        
                        // Mettre à jour derniereDateDon du Donateur si le don est apte
                        $donateur = $don->getDonateurId();
                            if ($donateur && $don->isApte() === true) {
                            $donateur->setDerniereDateDon($don->getDatedon());
                            $entityManager->persist($donateur);
                        }
                        
                        $entityManager->flush();

                            $message = 'Don validé avec succès pour ' . $donateur->getPrenom();
                            if ($don->isApte()) {
                                $message .= ' (Donateur apte - Date de dernier don mise à jour)';
                            } else {
                                $message .= ' (Donateur non apte)';
                            }
                            
                            $this->addFlash('success', $message);

                        return $this->redirectToRoute('admin_don_valider');
                    }
                    
                    $forms[$rdvId] = $form->createView();
                    }
                }
            }
        }

        // Créer les formulaires pour les autres rendez-vous
        foreach ($rendezVous as $rdv) {
            if (!isset($forms[$rdv->getId()])) {
                $don = new Don();
                $don->setRendezVous($rdv);
                $don->setDonateurId($rdv->getDonateur());
                $don->setDatedon(new \DateTime());
                $don->setApte(false); // Valeur par défaut : non apte
                
                $form = $this->createForm(DonType::class, $don);
                $forms[$rdv->getId()] = $form->createView();
            }
        }

        return $this->render('admin/don/valider.html.twig', [
            'rendezVous' => $rendezVous,
            'forms' => $forms,
        ]);
    }
}