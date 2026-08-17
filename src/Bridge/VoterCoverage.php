<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Ce que {@see VoterSurvey} a constaté : qui juge quoi, et ce qui n'a pas pu être demandé.
 *
 * Un constat, et aucune conclusion : rien ici ne dit si l'installation se tient. Une même
 * observation ne se conclut pas de la même façon selon qui la lit — la ligne de commande rend
 * un code de sortie, un panneau colore une icône — et loger la conclusion ici obligerait les
 * deux à s'entendre sur une sévérité qu'ils ne partagent pas.
 */
final readonly class VoterCoverage
{
    /**
     * @param array<string, list<string>> $judges   les voters qui se prononcent, par identité de droit
     * @param array<string, string>       $raised   ce qu'a levé chaque voter qui a levé, par classe
     * @param list<Permission>            $examined les droits soumis à l'examen, dans l'ordre du catalogue
     * @param int                         $voters   les voters enregistrés, comptés avant l'examen : un
     *                                              voter qui lève y figure, puisqu'il est bien installé
     */
    public function __construct(
        private array $judges,
        private array $raised,
        private array $examined,
        public int $voters,
    ) {
    }

    /** @return list<Permission> */
    public function examined(): array
    {
        return $this->examined;
    }

    /**
     * Les droits que personne ne prend en charge, et qui sont donc refusés à tout le monde,
     * administrateur compris, sans qu'aucune erreur ne soit levée nulle part.
     *
     * @return list<Permission>
     */
    public function unjudged(): array
    {
        return array_values(array_filter(
            $this->examined,
            fn (Permission $permission): bool => [] === ($this->judges[$permission->id()] ?? []),
        ));
    }

    /**
     * Les droits que plusieurs voters prennent en charge. Sous la stratégie « affirmative »,
     * celle de Symfony par défaut, il suffit qu'un accorde : le recouvrement élargit les droits,
     * il ne les restreint pas.
     *
     * @return array<string, list<string>> l'identité du droit, et les voters qui s'en saisissent
     */
    public function shared(): array
    {
        return array_filter($this->judges, static fn (array $judges): bool => \count($judges) > 1);
    }

    /**
     * Les voters qui ont levé quand on les a interrogés sans utilisateur.
     *
     * Tant qu'il y en a, le constat sur les orphelins n'est plus entier : un droit peut y
     * figurer parce que le seul voter qui l'aurait pris en charge n'a pas pu répondre.
     *
     * @return array<string, string> la classe du voter, et ce qu'il a levé
     */
    public function raised(): array
    {
        return $this->raised;
    }

    /** @return list<string> les voters qui se prononcent sur cette identité */
    public function judgesOf(string $id): array
    {
        return $this->judges[$id] ?? [];
    }
}
