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
        return $this->createQueryBuilder('r')
            ->leftJoin('App\Entity\Don', 'd', 'WITH', 'd.rendezVous = r.id')
            ->where('r.statut = :statut')
            ->andWhere('d.id IS NULL')
            ->setParameter('statut', 'Effectué')
            ->orderBy('r.dateHeureDebut', 'ASC')
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
