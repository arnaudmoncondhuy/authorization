<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Note ce que le contrat a répondu, et à qui, sans rien changer à ce qu'il répond.
 *
 * C'est ce que la barre de debug montre et que rien d'autre ne sait dire : le contrôle d'accès
 * de Symfony voit passer des attributs, il ne sait pas quel verbe métier les a demandés. Le
 * chaînon manquant est là, et c'est celui qu'on ne comprend pas au début.
 *
 * Il n'existe que là où le profileur est monté, c'est-à-dire en dev et en test. Une application
 * en production reçoit l'adaptateur nu, et ne paie rien pour ceci.
 */
final class TracingAuthorizer implements Authorizer
{
    /**
     * Assez pour couvrir un verbe qui réclame depuis une méthode privée, ou depuis un service
     * qu'il s'est donné. Au-delà, l'appel est rangé hors verbe : mieux vaut l'avouer que
     * remonter une pile entière pour deviner.
     */
    private const int DEPTH = 8;

    /** @var list<array{id: string, kind: string, granted: bool, caller: ?class-string}> */
    private array $calls = [];

    public function __construct(private readonly Authorizer $inner)
    {
    }

    public function can(Permission $permission): bool
    {
        $granted = $this->inner->can($permission);

        $this->note($permission, 'can', $granted);

        return $granted;
    }

    public function require(Permission $permission): void
    {
        try {
            $this->inner->require($permission);
        } catch (MissingPermission $refusal) {
            // Noté puis relancé tel quel : ce décorateur observe, il ne rattrape pas. Avaler le
            // refus ouvrirait en dev un verbe fermé en production, ce qui est exactement le
            // genre d'écart qu'un outil de mise au point ne doit jamais créer.
            $this->note($permission, 'require', false);

            throw $refusal;
        }

        $this->note($permission, 'require', true);
    }

    /**
     * L'adaptateur enveloppé, celui qui décide vraiment.
     *
     * Ce décorateur prend la place du contrat partout où il est monté, y compris sous les yeux
     * de {@see DoctorCommand}. Sans cette question, le docteur nommerait « contrat » celui qui
     * ne décide rien.
     *
     * @return class-string
     */
    public function wraps(): string
    {
        return $this->inner instanceof self ? $this->inner->wraps() : $this->inner::class;
    }

    /** @return list<array{id: string, kind: string, granted: bool, caller: ?class-string}> */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * Appelé entre deux requêtes d'un même processus — un worker, un serveur qui reste en vie.
     * Sans cela, ce qu'une page a demandé s'ajouterait à ce que la suivante demande.
     */
    public function reset(): void
    {
        $this->calls = [];
    }

    private function note(Permission $permission, string $kind, bool $granted): void
    {
        $this->calls[] = [
            'id' => $permission->id(),
            'kind' => $kind,
            'granted' => $granted,
            'caller' => $this->callingUseCase(),
        ];
    }

    /**
     * Le verbe métier qui a demandé, trouvé en remontant la pile.
     *
     * La pile est lue plutôt que le conteneur interrogé : envelopper chaque cas d'usage d'un
     * mandataire pour le savoir demanderait de reproduire sa signature, et un verbe s'appelle
     * aussi depuis un autre verbe, où le mandataire ne serait pas traversé.
     *
     * Nul quand l'appel ne vient d'aucun verbe. Ce n'est pas un échec de lecture mais un
     * constat : le dispositif veut qu'un droit se réclame dans un verbe, et une surface qui
     * réclame directement mérite d'être vue.
     *
     * @return ?class-string
     */
    private function callingUseCase(): ?string
    {
        foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, self::DEPTH) as $frame) {
            /** @var ?class-string $class */
            $class = $frame['class'] ?? null;

            if (null !== $class && is_a($class, UseCase::class, true)) {
                return $class;
            }
        }

        return null;
    }
}
