<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2019 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace App\Repository;

use App\Entity\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Webauthn\Bundle\Repository\AbstractPublicKeyCredentialUserEntityRepository;
use Webauthn\PublicKeyCredentialUserEntity;

final class PublicKeyCredentialUserEntityRepository extends AbstractPublicKeyCredentialUserEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function createUserEntity(string $username, string $displayName, ?string $icon): PublicKeyCredentialUserEntity
    {
        return new User($username, $displayName, [], $icon);
    }

    public function saveUserEntity(PublicKeyCredentialUserEntity $userEntity): void
    {
        if (!$userEntity instanceof User) {
            $userEntity =  $this->createUserEntity(
                $userEntity->getName(),
                $userEntity->getDisplayName(),
                $userEntity->getIcon()
            );
        }

        parent::saveUserEntity($userEntity);
    }

    public function find(string $username): ?User
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        return $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.name = :name')
            ->setParameter(':name', $username)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }
}
