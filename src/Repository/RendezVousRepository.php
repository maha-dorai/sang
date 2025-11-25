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
    
    /**
     * CORRECTION : Affiche TOUS les rendez-vous "Effectué" (avec ou sans don)
     * Pour permettre la validation et la revalidation
     */
    public function findEffectuesSansDon(): array
    {
        $em = $this->getEntityManager();
        
        // Récupérer TOUS les rendez-vous "Effectué" avec leurs relations
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.donateur', 'd')
            ->addSelect('d')
            ->leftJoin('r.collecte', 'c')
            ->addSelect('c')
            ->leftJoin('c.lieu', 'l')
            ->addSelect('l')
            ->where('TRIM(r.statut) LIKE :statut')
            ->setParameter('statut', '%Effectu%')
            ->orderBy('r.dateHeureDebut', 'ASC');
        
        $rendezVous = $qb->getQuery()->getResult();
        
        return $rendezVous;
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
            ->where('r.statut LIKE :statut')
            ->setParameter('statut', '%Effectu%')
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
}