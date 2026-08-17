<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\CallsNoUseCase;
use ArnaudMoncondhuy\Authorization\Permission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Examine une installation et refuse de conclure au vert sans raison.
 *
 * Ce qu'elle regarde, {@see VoterSurvey} le constate ; le voter qu'elle propose,
 * {@see VoterSketch} l'écrit. Elle-même ne fait que deux choses : le mettre en mots, et en
 * tirer un code de sortie.
 *
 * Ce partage n'est pas une commodité. Le même constat se lit aussi dans la barre de debug, et
 * deux examens séparés finiraient par répondre différemment à la même question — l'un dirait
 * qu'un droit a un juge, l'autre non, et rien ne dirait lequel a raison.
 *
 * Elle échoue plutôt que d'afficher : une routine qualité ne peut s'appuyer que sur ce qui
 * rend un code de sortie. Ce qui échoue est une décision de la commande et non de l'examen —
 * un recouvrement n'est pas toujours une faute, et un panneau qui le signale n'a pas à
 * s'interrompre pour autant.
 */
#[CallsNoUseCase("Examine une installation : elle lit le catalogue et interroge les voters, elle n'exerce aucun verbe métier.")]
#[AsCommand(
    name: 'authorization:doctor',
    description: 'Vérifie qu\'une installation du dispositif d\'autorisation se tient.',
)]
final class DoctorCommand extends Command
{
    /**
     * @param ?class-string $onBehalf  l'adaptateur qui répond sur un tiers, ou nul quand
     *                                 la configuration de sécurité ne déclare aucun
     *                                 fournisseur de comptes. Le nom et non le service :
     *                                 l'injecter le compterait utilisé, et
     *                                 {@see \ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass}
     *                                 ferait échouer la compilation ici même
     * @param ?string       $directory le service de fournisseur de comptes où cet adaptateur
     *                                 cherche : la chaîne de tous ceux que l'application
     *                                 déclare, ou le seul qu'elle a nommé
     */
    public function __construct(
        private readonly VoterSurvey $survey,
        private readonly VoterSketch $sketch,
        private readonly Authorizer $access,
        private readonly ?string $onBehalf = null,
        private readonly ?string $directory = null,
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
        $coverage = $this->survey->examine();

        $console->writeln(\sprintf('Contrat  : %s', $this->contract()));
        $console->writeln(\sprintf('Tiers    : %s', $this->onBehalf
            ?? 'non branché — la configuration de sécurité ne déclare aucun fournisseur de comptes'));

        // Sans contrat branché, il n'y a pas d'annuaire à nommer : la ligne dirait un service
        // que personne n'interroge.
        if (null !== $this->onBehalf && null !== $this->directory) {
            $console->writeln(\sprintf('Annuaire : %s', $this->directory));
        }

        $console->writeln(\sprintf('Voters   : %d enregistré(s)', $coverage->voters));

        $permissions = $coverage->examined();

        if ([] === $permissions) {
            // Ni un succès ni un échec : il n'y a rien à examiner. L'écrire plutôt que de
            // rendre un vert franc, qui laisserait croire qu'une installation a été vérifiée.
            $console->writeln('Droits   : aucun');
            $console->note('Aucun cas d\'usage ne déclare de droit : il n\'y a rien à examiner ici.');

            return Command::SUCCESS;
        }

        $console->writeln(\sprintf('Droits   : %d', \count($permissions)));
        $console->newLine();

        $raised = $coverage->raised();
        $unjudged = $coverage->unjudged();
        $shared = $coverage->shared();

        if ([] !== $raised) {
            $console->error('Des voters ont levé une exception pendant l\'examen :');
            foreach ($raised as $voter => $trouble) {
                $console->writeln(\sprintf('  %s — %s', $voter, $trouble));
            }
            $console->writeln('L\'examen les interroge avec un jeton sans utilisateur, qui est celui d\'une');
            $console->writeln('requête anonyme. Un voter qui lève ici lèvera là-bas : la surface rendra une');
            $console->writeln('erreur serveur au lieu d\'un refus. Garder l\'absence d\'utilisateur dans');
            $console->writeln('`supports()` ou en tête de `voteOnAttribute()` ferme les deux à la fois.');
        }

        if ([] !== $unjudged) {
            $console->error('Des droits ne sont jugés par personne, et sont donc refusés à tout le monde :');
            $console->listing(array_map(static fn (Permission $permission): string => $permission->id(), $unjudged));
            $console->writeln('Un voter doit reconnaître ces identités dans son `supports()`, sinon les');
            $console->writeln('verbes qui les exigent restent fermés, y compris pour un administrateur.');

            if ([] !== $raised) {
                $console->writeln('');
                $console->writeln('⚠ Un voter au moins n\'a pas pu être examiné : cette liste peut nommer un');
                $console->writeln('  droit qu\'il aurait pris en charge. La lire après avoir réparé ce qui lève.');
            }

            $this->propose($console, $unjudged);
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

        // Un voter qui lève fait échouer au même titre qu'un droit orphelin, et pour la raison
        // qui fonde cette commande : l'examen n'a pas eu lieu. Rendre SUCCESS ici certifierait
        // une installation qu'on n'a pas su regarder.
        if ([] !== $unjudged || [] !== $raised || ([] !== $shared && $input->getOption('strict'))) {
            return Command::FAILURE;
        }

        if ([] === $shared) {
            $console->success(\sprintf('Les %d droits déclarés ont chacun exactement un voter pour en juger.', \count($permissions)));
        }

        return Command::SUCCESS;
    }

    /**
     * L'adaptateur qui décide.
     *
     * Là où le profileur est monté, {@see TracingAuthorizer} a pris la place du contrat pour
     * l'observer. Le nommer ici dirait « contrat » de celui qui ne décide rien, et cacherait
     * celui qui décide — la commande tourne en dev, où le décorateur est justement là.
     */
    private function contract(): string
    {
        return $this->access instanceof TracingAuthorizer ? $this->access->wraps() : $this->access::class;
    }

    /**
     * Le squelette du voter manquant, précédé de ce qu'il faut savoir avant de le reprendre.
     *
     * @param list<Permission> $unjudged
     */
    private function propose(SymfonyStyle $console, array $unjudged): void
    {
        $console->newLine();
        $console->writeln('Un voter qui les prend en charge ressemblerait à ceci. Le nom et l\'espace de noms');
        $console->writeln('sont une proposition ; les identités ne le sont pas — ce sont celles qui manquent');
        $console->writeln('de juge, et elles seules. Reprendre l\'énumération entière donnerait un second juge');
        $console->writeln('aux droits qui en ont déjà un, ce qui les élargit au lieu de les fermer.');

        foreach ($this->sketch->of($unjudged) as $path => $sketch) {
            $console->newLine();
            $console->writeln(\sprintf('# %s', $path));
            $console->newLine();
            // Sans mise en forme : c'est du code destiné à être repris tel quel, et le
            // formateur de la console lit `<...>` comme une balise de style.
            $console->writeln($sketch, OutputInterface::OUTPUT_RAW);
        }
    }
}
