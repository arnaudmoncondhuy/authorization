<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\CallsNoUseCase;
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
 * Elle cherche trois choses qu'aucun autre contrôle ne voit.
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
 * **Un voter qui lève quand on l'interroge sans utilisateur** : l'examen se joue avec un jeton
 * vide, qui est celui d'une requête anonyme. Un voter qui y lève lèvera aussi en production, et
 * la surface rendra une erreur serveur au lieu d'un refus. Il est rapporté, l'examen continue,
 * et le bilan dit que la conclusion sur les orphelins n'est plus entière.
 *
 * Aux droits sans juge, elle joint le squelette du voter qui les prendrait en charge. Le nom et
 * l'espace de noms y sont une proposition ; les identités, non — elle n'y porte que celles qui
 * manquent de juge, parce que reprendre l'énumération entière donnerait un second juge à celles
 * qui en ont déjà un, et que sous `affirmative` un recouvrement élargit les droits.
 *
 * Le squelette est **affiché, jamais écrit**, et il n'accorde rien : quel voter prend en charge
 * quel droit, et qui l'obtient, sont des décisions qui appartiennent au projet.
 *
 * Elle échoue plutôt que d'afficher : une routine qualité ne peut s'appuyer que sur ce qui
 * rend un code de sortie.
 */
#[CallsNoUseCase("Examine une installation : elle lit le catalogue et interroge les voters, elle n'exerce aucun verbe métier.")]
#[AsCommand(
    name: 'authorization:doctor',
    description: 'Vérifie qu\'une installation du dispositif d\'autorisation se tient.',
)]
final class DoctorCommand extends Command
{
    /**
     * @param iterable<VoterInterface> $voters
     * @param ?class-string            $onBehalf  l'adaptateur qui répond sur un tiers, ou nul quand
     *                                            la configuration de sécurité ne déclare aucun
     *                                            fournisseur de comptes. Le nom et non le service :
     *                                            l'injecter le compterait utilisé, et
     *                                            {@see \ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass}
     *                                            ferait échouer la compilation ici même
     * @param ?string                  $directory le service de fournisseur de comptes où cet
     *                                            adaptateur cherche : la chaîne de tous ceux que
     *                                            l'application déclare, ou le seul qu'elle a nommé
     */
    public function __construct(
        private readonly PermissionCatalog $catalog,
        private readonly Authorizer $access,
        private readonly iterable $voters,
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

        $console->writeln(\sprintf('Contrat  : %s', $this->access::class));
        $console->writeln(\sprintf('Tiers    : %s', $this->onBehalf
            ?? 'non branché — la configuration de sécurité ne déclare aucun fournisseur de comptes'));

        // Sans contrat branché, il n'y a pas d'annuaire à nommer : la ligne dirait un service
        // que personne n'interroge.
        if (null !== $this->onBehalf && null !== $this->directory) {
            $console->writeln(\sprintf('Annuaire : %s', $this->directory));
        }

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
        /** @var array<string, string> $raised */
        $raised = [];

        foreach ($permissions as $permission) {
            $judges[$permission->id()] = $this->judgesOf($permission, $raised);
        }

        $unjudged = array_values(array_filter(
            $permissions,
            static fn (Permission $permission): bool => [] === $judges[$permission->id()],
        ));
        $shared = array_filter($judges, static fn (array $j): bool => \count($j) > 1);

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

            $this->sketch($console, $unjudged);
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
     * Le voter qui manque, écrit, pour n'avoir plus qu'à décider.
     *
     * Un squelette par contexte, le contexte étant l'énumération qui déclare le droit : c'est
     * le découpage que le paquet demande déjà, et deux contextes dans un même `supports()`
     * feraient d'un droit de facturation l'affaire du voter des stocks.
     *
     * @param list<Permission> $unjudged
     */
    private function sketch(SymfonyStyle $console, array $unjudged): void
    {
        $console->newLine();
        $console->writeln('Un voter qui les prend en charge ressemblerait à ceci. Le nom et l\'espace de noms');
        $console->writeln('sont une proposition ; les identités ne le sont pas — ce sont celles qui manquent');
        $console->writeln('de juge, et elles seules. Reprendre l\'énumération entière donnerait un second juge');
        $console->writeln('aux droits qui en ont déjà un, ce qui les élargit au lieu de les fermer.');

        foreach ($this->sketches($unjudged) as $path => $sketch) {
            $console->newLine();
            $console->writeln(\sprintf('# %s', $path));
            $console->newLine();
            // Sans mise en forme : c'est du code destiné à être repris tel quel, et le
            // formateur de la console lit `<...>` comme une balise de style.
            $console->writeln($sketch, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * @param list<Permission> $unjudged
     *
     * @return array<string, string> le chemin proposé, et le code qui va dedans
     */
    private function sketches(array $unjudged): array
    {
        /** @var array<class-string, list<string>> $contexts */
        $contexts = [];

        foreach ($unjudged as $permission) {
            $contexts[$permission::class][] = $permission->id();
        }

        $sketches = [];

        foreach ($contexts as $context => $ids) {
            $name = $this->voterName($context);
            $sketches[\sprintf('src/Security/%s.php', $name)] = $this->sketchOf($name, $ids);
        }

        return $sketches;
    }

    /**
     * Le refus explicite plutôt que l'abstention, aux deux endroits où il compte : le jeton
     * sans utilisateur est celui d'une requête anonyme, et une abstention y fermerait aussi le
     * verbe à toute surface sans session ; la règle laissée à écrire refuse, parce qu'un
     * squelette qui accorderait ouvrirait le droit à la seconde où il est collé.
     *
     * @param list<string> $ids
     */
    private function sketchOf(string $name, array $ids): string
    {
        $listed = implode("\n", array_map(
            static fn (string $id): string => \sprintf("            '%s',", $id),
            $ids,
        ));

        return strtr(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Security;

            use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
            use Symfony\Component\Security\Core\Authorization\Voter\Vote;
            use Symfony\Component\Security\Core\Authorization\Voter\Voter;
            use Symfony\Component\Security\Core\User\UserInterface;

            /** @extends Voter<string, mixed> */
            final class {name} extends Voter
            {
                protected function supports(string $attribute, mixed $subject): bool
                {
                    return \in_array($attribute, [
            {ids}
                    ], true);
                }

                protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
                {
                    // Le jeton d'une requête anonyme ne porte pas d'utilisateur. Refuser, plutôt
                    // que s'abstenir : une abstention fermerait aussi le verbe aux surfaces sans
                    // session — console, worker, tâche planifiée.
                    if (!$token->getUser() instanceof UserInterface) {
                        return false;
                    }

                    // À écrire : qui détient ce droit. Tel quel, il reste refusé à tout le monde
                    // — mais il a désormais un juge, et le docteur ne le nommera plus.
                    return false;
                }
            }
            PHP, ['{name}' => $name, '{ids}' => $listed]);
    }

    /**
     * « InvoicePermission » donne « InvoiceVoter ». Une proposition, et rien de plus : le
     * paquet ne connaît ni l'arborescence de l'application ni ses conventions de nommage.
     *
     * @param class-string $context
     */
    private function voterName(string $context): string
    {
        $parts = explode('\\', $context);
        $short = end($parts);

        // Une énumération nommée « Permission » tout court ne laisserait rien à préfixer.
        $stem = preg_replace('/Permissions?$/', '', $short);

        return ('' === $stem || null === $stem ? $short : $stem).'Voter';
    }

    /**
     * Les voters qui se prononcent sur cette identité. Un voter qui s'abstient ne la prend pas
     * en charge : sur un voter bâti sur la classe abstraite de Symfony, c'est exact —
     * l'abstention y est la réponse lorsque `supports()` refuse. Un voter qui s'abstient pour
     * une autre raison, l'absence d'utilisateur par exemple, serait compté absent à tort.
     *
     * Un voter qui lève est retenu comme tel, et l'examen continue. Laisser filer l'exception
     * ferait tomber la commande sur le premier voter fragile, et les droits suivants ne
     * seraient jamais regardés — un diagnostic qui s'interrompt en dit moins qu'un diagnostic
     * qui rapporte. Il n'est compté ni juge ni absent : ce qu'il aurait répondu reste inconnu,
     * et c'est cela qu'il faut dire.
     *
     * @param array<string, string> $raised recueille les voters qui ont levé, par classe
     *
     * @return list<string>
     */
    private function judgesOf(Permission $permission, array &$raised): array
    {
        $nobody = new NullToken();
        $judges = [];

        foreach ($this->registeredVoters() as $voter) {
            // Le premier incident suffit à disqualifier le voter : réinterroger celui qui vient
            // de lever sur les droits suivants n'apprendrait rien et allongerait le rapport.
            if (isset($raised[$voter::class])) {
                continue;
            }

            try {
                $vote = $voter->vote($nobody, null, [$permission->id()]);
            } catch (\Throwable $trouble) {
                $raised[$voter::class] = \sprintf('%s : %s', $trouble::class, $trouble->getMessage());
                continue;
            }

            if (VoterInterface::ACCESS_ABSTAIN !== $vote) {
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
