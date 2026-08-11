<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Examine une installation et refuse de conclure au vert sans raison.
 *
 * Ce qu'elle cherche, aucun autre contrôle ne le voit : **un droit qu'aucun voter ne prend en
 * charge**. Le code l'exige, l'inventaire le propose, l'écran d'attribution permet de le
 * cocher — et il est refusé à tout le monde, administrateur compris, sans qu'aucune erreur ne
 * soit levée nulle part. C'est la faute la plus silencieuse du dispositif.
 *
 * Elle échoue plutôt que d'afficher : une routine qualité ne peut s'appuyer que sur ce qui
 * rend un code de sortie.
 */
#[AsCommand(
    name: 'authorization:doctor',
    description: 'Vérifie qu\'une installation du dispositif d\'autorisation se tient.',
)]
final class DoctorCommand extends Command
{
    /** @param iterable<VoterInterface> $voters */
    public function __construct(
        private readonly PermissionCatalog $catalog,
        private readonly Authorizer $access,
        private readonly iterable $voters,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);

        $console->writeln(\sprintf('Contrat  : %s', $this->access::class));
        $console->writeln(\sprintf('Voters   : %d enregistré(s)', \count($this->registeredVoters())));

        $permissions = $this->catalog->all();

        if ([] === $permissions) {
            // Ni un succès ni un échec : il n'y a rien à examiner. L'écrire plutôt que de
            // rendre un vert franc, qui laisserait croire qu'une installation a été vérifiée.
            $console->writeln('Droits   : aucun');
            $console->note('Aucun cas d\'usage ne déclare de droit : il n\'y a rien à examiner ici.');

            return Command::SUCCESS;
        }

        $console->writeln(\sprintf('Droits   : %d', \count($permissions)));
        $console->newLine();

        $orphans = array_values(array_filter($permissions, fn (Permission $p): bool => !$this->hasAJudge($p)));

        if ([] === $orphans) {
            $console->success(\sprintf('Les %d droits déclarés ont chacun au moins un voter pour en juger.', \count($permissions)));

            return Command::SUCCESS;
        }

        $console->error('Des droits ne sont jugés par personne, et sont donc refusés à tout le monde :');
        $console->listing(array_map(static fn (Permission $p): string => $p->id(), $orphans));
        $console->writeln('Un voter doit reconnaître ces identités dans son `supports()`, sinon les');
        $console->writeln('verbes qui les exigent restent fermés, y compris pour un administrateur.');

        return Command::FAILURE;
    }

    /**
     * Un voter qui s'abstient sur une identité ne la prend pas en charge. Sur un voter bâti
     * sur la classe abstraite de Symfony, c'est exact : l'abstention y est la réponse
     * lorsque `supports()` refuse. Un voter qui s'abstient pour une autre raison — l'absence
     * d'utilisateur, par exemple — serait compté à tort comme absent.
     */
    private function hasAJudge(Permission $permission): bool
    {
        $nobody = new NullToken();

        foreach ($this->registeredVoters() as $voter) {
            if (VoterInterface::ACCESS_ABSTAIN !== $voter->vote($nobody, null, [$permission->id()])) {
                return true;
            }
        }

        return false;
    }

    /** @return list<VoterInterface> */
    private function registeredVoters(): array
    {
        return array_values([...$this->voters]);
    }
}
