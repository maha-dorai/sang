<?php

namespace App\Repository;

use App\Entity\RendezVous;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    public function findByDonateurId(int $donateurId){
        return $this->createQueryBuilder('r')
            ->join('r.donateur' , 'd')
            ->addSelect('d')
            ->andWhere('d.id = :donateurId')
            ->setParameter('donateurId', $donateurId)
           ->getQuery()
           ->getResult();


    }  
    public function findEffectuesSansDon(): array
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        
        // Utiliser une requête SQL directe avec LEFT JOIN pour trouver les rendez-vous "Effectué" sans Don
        // C'est la méthode la plus fiable
        $rdvMetadata = $em->getClassMetadata('App\Entity\RendezVous');
        $donMetadata = $em->getClassMetadata('App\Entity\Don');
        $rdvTable = $rdvMetadata->getTableName();
        $donTable = $donMetadata->getTableName();
        $rdvIdColumn = $rdvMetadata->getSingleIdentifierColumnName();
        
        // Récupérer le nom de la colonne de jointure
        $donRdvMapping = $donMetadata->getAssociationMapping('rendezVous');
        $donRdvColumn = $donRdvMapping['joinColumns'][0]['name'] ?? 'rendez_vous_id';
        
        // Récupérer le nom de la colonne de date
        $dateColumnMapping = $rdvMetadata->getFieldMapping('dateHeureDebut');
        $dateColumn = $dateColumnMapping['columnName'] ?? 'date_heure_debut';
        
        // Requête SQL directe pour trouver les rendez-vous "Effectué" sans Don
        $sql = "SELECT r.{$rdvIdColumn} as id
                FROM {$rdvTable} r
                LEFT JOIN {$donTable} d ON d.{$donRdvColumn} = r.{$rdvIdColumn}
                WHERE LOWER(TRIM(r.statut)) IN ('effectué', 'effectue', 'effectuee')
                AND d.id IS NULL
                ORDER BY r.{$dateColumn} ASC";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $rows = $result->fetchAllAssociative();
        
        $rdvIds = array_column($rows, 'id');
        
        if (empty($rdvIds)) {
            return [];
        }
        
        // Récupérer les rendez-vous avec leurs relations
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.donateur', 'd')
            ->addSelect('d')
            ->leftJoin('r.collecte', 'c')
            ->addSelect('c')
            ->leftJoin('c.lieu', 'l')
            ->addSelect('l')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $rdvIds)
            ->orderBy('r.dateHeureDebut', 'ASC');
        
        $rendezVousSansDon = $qb->getQuery()->getResult();
        
        return $rendezVousSansDon;
    }
    
    /**
     * Méthode de débogage pour vérifier les rendez-vous "Effectué"
     * Retourne tous les rendez-vous avec statut "Effectué" sans filtre sur les Dons
     */
    public function findAllEffectues(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.donateur', 'd')
            ->addSelect('d')
            ->leftJoin('r.collecte', 'c')
            ->addSelect('c')
            ->leftJoin('c.lieu', 'l')
            ->addSelect('l')
            ->where('r.statut = :statut')
            ->setParameter('statut', 'Effectué')
            ->orderBy('r.dateHeureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Méthode de débogage : récupère tous les rendez-vous avec leur statut
     */
    public function findAllWithStatut(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id, r.statut')
            ->getQuery()
            ->getResult();
    }
    
    //    /**
    //     * @return RendezVous[] Returns an array of RendezVous objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?RendezVous
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }


}
