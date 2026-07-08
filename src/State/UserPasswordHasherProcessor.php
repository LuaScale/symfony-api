<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordHasherProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private readonly ProcessorInterface $persistProcessor,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User && $data->getPassword() !== null && $data->getPassword() !== '') {
            $data->setPassword(
                $this->passwordHasher->hashPassword($data, $data->getPassword())
            );
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
