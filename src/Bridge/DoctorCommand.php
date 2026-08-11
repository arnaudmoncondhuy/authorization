<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Examine une installation et refuse de conclure au vert sans raison.
 *
 * Elle cherche deux choses qu'aucun autre contrôle ne voit.
 *
 * **Un droit qu'aucun voter ne prend en charge** : le code l'exige, l'inventaire le propose,
 * l'écran d'attribution permet de le cocher — et il est refusé à tout le monde, administrateur
 * compris, sans qu'aucune erreur ne soit levée nulle part.
 *
 * **Un droit que plusieurs voters prennent en charge** : sous la stratégie `affirmative`, celle
 * de Symfony par défaut, il suffit qu'**un** accorde pour que l'accès passe, et les refus des
 * autres ne sont pas consultés. Deux modèles qui se recouvrent n'aboutissent donc pas au plus
 * strict des deux mais au plus permissif. Ce n'est pas toujours une faute — un voter qui ouvre
 * tout à une poignée d'administrateurs en est un usage légitime — d'où un signalement par
 * défaut, et un échec sur demande.
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

    protected function configure(): void
    {
        $this->addOption(
            'strict',
            null,
            InputOption::VALUE_NONE,
            'Fait échouer aussi lorsqu\'un droit est jugé par plusieurs voters.',
        );
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

        /** @var array<string, list<string>> $judges */
        $judges = [];

        foreach ($permissions as $permission) {
            $judges[$permission->id()] = $this->judgesOf($permission);
        }

        $orphans = array_keys(array_filter($judges, static fn (array $j): bool => [] === $j));
        $shared = array_filter($judges, static fn (array $j): bool => \count($j) > 1);

        if ([] !== $orphans) {
            $console->error('Des droits ne sont jugés par personne, et sont donc refusés à tout le monde :');
            $console->listing($orphans);
            $console->writeln('Un voter doit reconnaître ces identités dans son `supports()`, sinon les');
            $console->writeln('verbes qui les exigent restent fermés, y compris pour un administrateur.');
        }

        if ([] !== $shared) {
            $console->warning('Des droits sont jugés par plusieurs voters :');
            foreach ($shared as $id => $voters) {
                $console->writeln(\sprintf('  %s — %s', $id, implode(', ', $voters)));
            }
            $console->writeln('Sous la stratégie « affirmative », qui est celle de Symfony par défaut, il');
            $console->writeln('suffit qu\'un seul accorde : le recouvrement élargit les droits, il ne les');
            $console->writeln('restreint pas. Dériver tous les `supports()` d\'une même répartition ferme');
            $console->writeln('la question.');
        }

        if ([] !== $orphans || ([] !== $shared && $input->getOption('strict'))) {
            return Command::FAILURE;
        }

        if ([] === $shared) {
            $console->success(\sprintf('Les %d droits déclarés ont chacun exactement un voter pour en juger.', \count($permissions)));
        }

        return Command::SUCCESS;
    }

    /**
     * Les voters qui se prononcent sur cette identité. Un voter qui s'abstient ne la prend pas
     * en charge : sur un voter bâti sur la classe abstraite de Symfony, c'est exact —
     * l'abstention y est la réponse lorsque `supports()` refuse. Un voter qui s'abstient pour
     * une autre raison, l'absence d'utilisateur par exemple, serait compté absent à tort.
     *
     * @return list<string>
     */
    private function judgesOf(Permission $permission): array
    {
        $nobody = new NullToken();
        $judges = [];

        foreach ($this->registeredVoters() as $voter) {
            if (VoterInterface::ACCESS_ABSTAIN !== $voter->vote($nobody, null, [$permission->id()])) {
                $judges[] = $voter::class;
            }
        }

        return $judges;
    }

    /** @return list<VoterInterface> */
    private function registeredVoters(): array
    {
        return array_values([...$this->voters]);
    }
}
