<?php

namespace App\Repository;

use App\Entity\Recipe;
use App\Entity\RecipeTag;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog recipe information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    public function findLatest(int $page = 1): Pagerfanta
    {
        $query = $this->getEntityManager()
            ->createQuery('
                SELECT p, a, t
                FROM App:Recipe p
                JOIN p.author a
                LEFT JOIN p.recipeTags t
                WHERE p.createdAt <= :now
                AND p.private = 0
                ORDER BY p.createdAt DESC
            ')
            ->setParameter('now', new \DateTime())
        ;

        return $this->createPaginator($query, $page);
    }

    private function createPaginator(Query $query, int $page): Pagerfanta
    {
        $paginator = new Pagerfanta(new DoctrineORMAdapter($query));
        $paginator->setMaxPerPage(Recipe::NUM_ITEMS);
        $paginator->setCurrentPage($page);

        return $paginator;
    }


    public function getMyRecipes($user)
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder->leftJoin('r.collectors', 'c');
        $userGroup = $queryBuilder->expr()->orX();
        $userGroup->add('r.author = :user');
        $userGroup->add('c.id = :user');
        $queryBuilder->andWhere($userGroup);

        $queryBuilder->setParameter('user', $user);
        $queryBuilder->orderBy('r.createdAt', 'DESC');

        return $queryBuilder->getQuery()->getResult();
    }


    /**
     * @return Recipe[]
     */
    public function findBySearchQuery(string $rawQuery, int $limit = Recipe::NUM_ITEMS): array
    {
        $query = $this->sanitizeSearchQuery($rawQuery);
        $searchTerms = $this->extractSearchTerms($query);

        if (0 === count($searchTerms)) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('p');
        $queryBuilder->leftJoin('p.recipeTags', 't');

        $fields = $queryBuilder->expr()->orX();
        foreach ($searchTerms as $key => $term) {
            $fields->add('p.title LIKE :t_'.$key)
                ->add('p.summary LIKE :t_'.$key)
                ->add('t.name LIKE :t_'.$key);
        }

        $queryBuilder->andWhere($fields)
            ->setParameter('t_'.$key, '%'.$term.'%')
            ->andWhere('p.private = 0');

        return $queryBuilder
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Removes all non-alphanumeric characters except whitespaces.
     */
    private function sanitizeSearchQuery(string $query): string
    {
        return preg_replace('/[^[:alnum:] ]/', '', trim(preg_replace('/[[:space:]]+/', ' ', $query)));
    }

    /**
     * Splits the search query into terms and removes the ones which are irrelevant.
     */
    private function extractSearchTerms(string $searchQuery): array
    {
        $terms = array_unique(explode(' ', mb_strtolower($searchQuery)));

        return array_filter($terms, function ($term) {
            return 2 <= mb_strlen($term);
        });
    }

    private function applyRecipeTagsFilter(QueryBuilder $queryBuilder, $filter)
    {
        $fields = $queryBuilder->expr()->andX();
        $allKeywords = [];
        /**
         * @var int $key
         * @var RecipeTag $term
         */
        foreach ($filter['recipeTags'] ?? [] as $key => $term) {
            $allKeywords[] = $term->getId();
        }
        foreach ($filter['recipeTags'] ?? [] as $key => $term) {
            $fields->add('t.id in (:tags)');
            $queryBuilder->setParameter('tags', $allKeywords);
        }
        $queryBuilder->andWhere($fields);
        $queryBuilder->having('count(distinct t.id) >= '.count($allKeywords));
        $queryBuilder->addGroupBy('r.id');
    }

    public function filterRecipes($page, $filter = [], ?User $user = null)
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder->leftJoin('r.recipeTags', 't');
        QueryHelper::andWhereFromFilter($queryBuilder, $filter, 'private', 'r.author');
        $this->applyRecipeTagsFilter($queryBuilder, $filter);


        return $this->createPaginator($queryBuilder->getQuery(), $page);
    }

    public function getAllForFilter($filter = [], ?User $user = null)
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder->leftJoin('r.recipeTags', 't');
        QueryHelper::andWhereFromFilter($queryBuilder, $filter, 'private', 'r.author');
        $this->applyRecipeTagsFilter($queryBuilder, $filter);

        return $queryBuilder->getQuery()->getResult();
    }
}
