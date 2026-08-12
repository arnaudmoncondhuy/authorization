<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\CallsNoUseCase;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Affiche les droits que le code exige.
 *
 * L'inventaire n'existe aujourd'hui que dans un service injecté : sans cette commande, la
 * seule façon de savoir ce que l'application réclame est de lire toutes ses classes.
 */
#[CallsNoUseCase("Affiche l'inventaire : elle ne fait qu'énumérer ce que le code exige.")]
#[AsCommand(
    name: 'authorization:permissions',
    description: 'Liste les droits que les cas d\'usage exigent.',
)]
final class PermissionsCommand extends Command
{
    public function __construct(private readonly PermissionCatalog $catalog)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $permissions = $this->catalog->all();

        if ([] === $permissions) {
            $console->writeln('Aucun droit déclaré.');

            return Command::SUCCESS;
        }

        $console->table(
            ['Identité', 'Déclaré par'],
            array_map(
                static fn ($permission): array => [$permission->id(), self::declaredBy($permission)],
                $permissions,
            ),
        );

        $console->writeln(\sprintf('%d droit(s).', \count($permissions)));

        return Command::SUCCESS;
    }

    /**
     * D'où vient l'identité, pour qu'on sache où la changer — et pour que deux contextes de
     * même nom court restent distinguables.
     */
    private static function declaredBy(object $permission): string
    {
        return $permission instanceof \UnitEnum
            ? $permission::class.'::'.$permission->name
            : $permission::class;
    }
}
