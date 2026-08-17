<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Ce que la barre de debug montre du dispositif.
 *
 * Elle répond à deux questions que rien d'autre ne réunit.
 *
 * **Ce qui s'est passé sur cette page** : quels verbes métier ont été traversés, ce que chacun
 * déclare, ce qu'il a réclamé, et ce qui lui a été accordé. Le panneau « Security » de Symfony
 * montre déjà les votes ; ce qu'il ignore, c'est le verbe qui les a demandés — et c'est
 * justement ce chaînon qui rend le dispositif obscur au début.
 *
 * **Si l'installation se tient** : les droits que personne ne juge. La question est celle de
 * {@see DoctorCommand}, et la réponse vient du même {@see VoterSurvey} : deux examens séparés
 * finiraient par se contredire, et rien ne dirait lequel a raison.
 *
 * Ce qu'elle rapporte est daté de la requête. « Pas sur cette page » ne veut pas dire « jamais
 * dans le code » : un droit déclaré qu'aucune page ne réclame est l'affaire de
 * {@see \ArnaudMoncondhuy\Authorization\Testing\PermissionUsage}, en test, qui lit les corps de
 * méthode et non une requête.
 */
final class AuthorizationCollector extends DataCollector
{
    public const string NAME = 'authorization';

    /**
     * @param ?class-string $onBehalf  l'adaptateur qui répond sur un tiers, ou nul quand la
     *                                 configuration de sécurité ne déclare aucun fournisseur
     *                                 de comptes. Le nom et non le service, pour la raison
     *                                 qu'expose {@see \ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass}
     * @param ?string       $directory le service de fournisseur de comptes où cet adaptateur
     *                                 cherche
     */
    public function __construct(
        private readonly TracingAuthorizer $traced,
        private readonly VoterSurvey $survey,
        private readonly VoterSketch $sketch,
        private readonly PermissionCatalog $catalog,
        private readonly ?string $onBehalf = null,
        private readonly ?string $directory = null,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $calls = $this->traced->calls();
        $coverage = $this->survey->examine();
        $unjudged = $coverage->unjudged();

        $required = array_filter($calls, static fn (array $call): bool => 'require' === $call['kind']);
        $granted = array_filter($required, static fn (array $call): bool => $call['granted']);

        $this->data = [
            'contract' => $this->traced->wraps(),
            'onBehalf' => $this->onBehalf,
            'directory' => $this->directory,
            'voters' => $coverage->voters,
            'catalog' => \count($this->catalog->ids()),
            'touched' => \count(array_unique(array_column($calls, 'id'))),
            'granted' => \count($granted),
            'refused' => \count($required) - \count($granted),
            'verbs' => $this->verbsOf($calls),
            'unjudged' => array_map(static fn (Permission $permission): string => $permission->id(), $unjudged),
            'shared' => $coverage->shared(),
            'raised' => $coverage->raised(),
            'sketches' => $this->sketch->of($unjudged),
        ];
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Les verbes traversés, dans l'ordre où ils ont demandé pour la première fois.
     *
     * @return list<array{name: ?string, declared: list<string>, calls: list<array{id: string, kind: string, granted: bool}>, absent: list<string>}>
     */
    public function verbs(): array
    {
        /** @var list<array{name: ?string, declared: list<string>, calls: list<array{id: string, kind: string, granted: bool}>, absent: list<string>}> $verbs */
        $verbs = $this->data['verbs'] ?? [];

        return $verbs;
    }

    /** @return list<string> les droits que personne ne juge, et qui sont donc refusés à tout le monde */
    public function unjudged(): array
    {
        /** @var list<string> $unjudged */
        $unjudged = $this->data['unjudged'] ?? [];

        return $unjudged;
    }

    /** @return array<string, list<string>> les droits que plusieurs voters prennent en charge */
    public function shared(): array
    {
        /** @var array<string, list<string>> $shared */
        $shared = $this->data['shared'] ?? [];

        return $shared;
    }

    /** @return array<string, string> les voters qui ont levé pendant l'examen, et ce qu'ils ont levé */
    public function raised(): array
    {
        /** @var array<string, string> $raised */
        $raised = $this->data['raised'] ?? [];

        return $raised;
    }

    /** @return array<string, string> le voter qui manque, écrit : le chemin proposé, et le code */
    public function sketches(): array
    {
        /** @var array<string, string> $sketches */
        $sketches = $this->data['sketches'] ?? [];

        return $sketches;
    }

    /** Les droits réclamés et obtenus sur cette page. */
    public function granted(): int
    {
        return (int) ($this->data['granted'] ?? 0);
    }

    /** Les droits réclamés et refusés sur cette page, rattrapés ou non par l'application. */
    public function refused(): int
    {
        return (int) ($this->data['refused'] ?? 0);
    }

    /** Les droits que le code exige, tous contextes confondus. */
    public function catalog(): int
    {
        return (int) ($this->data['catalog'] ?? 0);
    }

    /** Ceux d'entre eux que cette page a touchés, réclamés ou seulement consultés. */
    public function touched(): int
    {
        return (int) ($this->data['touched'] ?? 0);
    }

    public function voters(): int
    {
        return (int) ($this->data['voters'] ?? 0);
    }

    /** L'adaptateur qui décide — celui qu'enveloppe le décorateur, et non le décorateur. */
    public function contract(): string
    {
        return (string) ($this->data['contract'] ?? '');
    }

    public function onBehalf(): ?string
    {
        $onBehalf = $this->data['onBehalf'] ?? null;

        return \is_string($onBehalf) ? $onBehalf : null;
    }

    public function directory(): ?string
    {
        $directory = $this->data['directory'] ?? null;

        return \is_string($directory) ? $directory : null;
    }

    /** Les droits qu'un verbe déclare et que cette page n'a pas touchés, tous verbes confondus. */
    public function absent(): int
    {
        return array_sum(array_map(static fn (array $verb): int => \count($verb['absent']), $this->verbs()));
    }

    /** Les droits que cette page a consultés sans les exiger, tous verbes confondus. */
    public function consulted(): int
    {
        return array_sum(array_map(
            static fn (array $verb): int => \count(array_filter(
                $verb['calls'],
                static fn (array $call): bool => 'require' !== $call['kind'],
            )),
            $this->verbs(),
        ));
    }

    /**
     * La couleur de l'icône, décidée ici plutôt que dans le gabarit : c'est une règle, et une
     * règle se lit et s'éprouve mieux en PHP qu'en Twig.
     *
     * Rouge à ce qui ferme un verbe : un refus sur la page, un droit que personne ne juge, un
     * voter qui n'a pas su répondre. Jaune à ce qui mérite un coup d'œil sans rien casser : un
     * droit déclaré que la page n'a pas touché, un droit jugé deux fois.
     */
    public function status(): string
    {
        if ($this->refused() > 0 || [] !== $this->unjudged() || [] !== $this->raised()) {
            return 'red';
        }

        if ($this->absent() > 0 || [] !== $this->shared()) {
            return 'yellow';
        }

        return '';
    }

    /**
     * Regroupe les appels par verbe, et joint à chacun ce que ses attributs déclarent.
     *
     * L'ordre est celui de la première demande : c'est celui dans lequel la page s'est jouée,
     * et le relire trié le rendrait moins fidèle à ce qui s'est passé.
     *
     * @param list<array{id: string, kind: string, granted: bool, caller: ?class-string}> $calls
     *
     * @return list<array{name: ?string, declared: list<string>, calls: list<array{id: string, kind: string, granted: bool}>, absent: list<string>}>
     */
    private function verbsOf(array $calls): array
    {
        /** @var array<string, array{name: ?string, declared: list<string>, calls: list<array{id: string, kind: string, granted: bool}>, absent: list<string>}> $verbs */
        $verbs = [];

        foreach ($calls as $call) {
            $caller = $call['caller'];
            // Les appels venus d'ailleurs qu'un verbe se rassemblent sous une seule entrée : ce
            // qui compte est qu'ils n'en traversent aucun, pas d'où ils viennent chacun.
            $key = $caller ?? '';

            $verbs[$key] ??= [
                'name' => $caller,
                'declared' => null === $caller ? [] : $this->declaredBy($caller),
                'calls' => [],
                'absent' => [],
            ];

            $verbs[$key]['calls'][] = ['id' => $call['id'], 'kind' => $call['kind'], 'granted' => $call['granted']];
        }

        foreach ($verbs as $key => $verb) {
            $touched = array_column($verb['calls'], 'id');

            $verbs[$key]['absent'] = array_values(array_diff($verb['declared'], $touched));
        }

        return array_values($verbs);
    }

    /**
     * Ce que les attributs du verbe déclarent. Lu par réflexion et à la collecte : le catalogue
     * sait quels droits le code exige, pas lequel des verbes exige lequel.
     *
     * @param class-string $verb
     *
     * @return list<string>
     */
    private function declaredBy(string $verb): array
    {
        $reflection = new \ReflectionClass($verb);

        return array_map(
            static fn (\ReflectionAttribute $attribute): string => $attribute->newInstance()->permission->id(),
            $reflection->getAttributes(RequiresPermission::class),
        );
    }
}
