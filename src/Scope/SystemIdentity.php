<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Scope;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Fait tourner un traitement sous une identité que l'application se donne à elle-même.
 *
 * Un cas d'usage réclame ses droits à l'appelant courant. Une commande de console, un worker
 * ou une tâche planifiée n'en ont pas : sans cette pièce, tous leurs verbes sont refusés, et
 * la promesse d'un même verbe atteignable par toutes les portes ne tient plus.
 *
 *     $this->system->run($this->identity(), fn () => ($this->importCatalog)($file));
 *
 * L'identité est **construite par l'application**, jamais ici : ce paquet ne sait pas qui elle
 * autorise, et n'a pas à le savoir. Ce qu'il apporte, c'est la portée — le jeton précédent est
 * rendu quoi qu'il arrive, y compris si le traitement lève.
 *
 * Un voter reste seul juge : donner une identité n'accorde rien. C'est à l'application de
 * décider ce qu'un rôle de service obtient, et de le décider aussi étroitement qu'un rôle
 * humain — un rôle de service qui accorde tout est une porte ouverte, pas une commodité.
 *
 * Elle vit hors de `Bridge\`, et c'est ce qui la rend utilisable : une surface a le droit de
 * l'injecter, là où l'adaptateur du contrôle d'accès et les passes de compilation doivent lui
 * rester fermés. Une architecture qui clôt `Bridge\` d'un bloc n'a rien à découper.
 */
final readonly class SystemIdentity
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(TokenInterface $identity, callable $work): mixed
    {
        $previous = $this->tokens->getToken();
        $this->tokens->setToken($identity);

        try {
            return $work();
        } finally {
            $this->tokens->setToken($previous);
        }
    }
}
