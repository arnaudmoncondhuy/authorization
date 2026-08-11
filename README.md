# arnaudmoncondhuy/authorization

Un droit se déclare **une fois**, sur le verbe métier. Le conteneur refuse de compiler sinon,
et l'inventaire des droits se dérive du code.

```php
#[RequiresPermission(InvoicePermission::Finalize)]
#[RequiresPermission(InvoicePermission::Backdate)]
final readonly class FinalizeInvoiceUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(string $number, ?\DateTimeImmutable $backdatedTo = null): void
    {
        $this->access->require(InvoicePermission::Finalize);

        if (null !== $backdatedTo) {
            $this->access->require(InvoicePermission::Backdate);
        }
    }
}
```

Une page, une API, un outil pour IA, une commande : toutes appellent ce verbe, aucune ne
redéclare ses droits. C'est ce qui fait qu'une capacité ne peut pas diverger d'une surface à
l'autre — il n'y a qu'un endroit où elle est écrite.

## Ce que le paquet garantit

Trois règles, et chacune **arrête la compilation du conteneur**. Pas un contrôle
d'intégration continue qu'on peut contourner : l'application ne démarre pas, y compris sur le
poste de qui a écrit la faute.

| Règle | Ce qu'elle empêche |
|---|---|
| Tout cas d'usage déclare au moins un droit | un verbe qui s'exécute sans arbitrage, et qu'on ne peut accorder à personne |
| Nul autre qu'un cas d'usage n'en déclare | une surface qui durcit son côté sans toucher au verbe, et l'inverse |
| Deux droits distincts ne partagent jamais une identité | accorder un droit dans un contexte l'accorder dans l'autre — la collision se juge sur la valeur, donc entre deux cas d'une même énumération autant qu'entre deux énumérations |

**Ces trois règles ne jugent que ce qui implémente `UseCase`.** Une classe qui oublie
l'interface leur échappe entièrement, tout en pouvant réclamer des droits. Le langage ne sait
pas l'empêcher ; `PermissionUsage` le rattrape en test, et c'est écrit ici plutôt que tu, parce
que c'est la seule brèche du dispositif.

Deux contrôles ne peuvent pas arrêter la compilation et se jouent donc ailleurs :

- rapprocher ce que l'attribut **déclare** de ce que le corps **réclame** demande de lire un
  corps de méthode — `PermissionUsage`, en test ;
- savoir si un droit trouve **quelqu'un pour en juger** demande d'interroger les voters
  installés — `authorization:doctor`, en ligne de commande.

## Ce que le paquet ne fait pas

**Il ne décide rien.** Savoir si l'utilisateur courant détient `invoice.finalize` reste
l'affaire d'un voter que vous écrivez. Ce paquet garantit seulement qu'aucune surface ne peut
exposer un verbe métier sans que le droit correspondant soit nommé, unique, et réclamé dans le
corps de la méthode.

Il ne fournit ni stockage des droits, ni modèle de rôles, ni écran d'administration. Le modèle
appartient au projet : rôles, groupes, droits par compte, ou les trois à la fois — le paquet
n'en sait rien et n'a pas à en savoir.

## Installation

```bash
composer require arnaudmoncondhuy/authorization
```

Aucune recette Flex : le bundle s'enregistre à la main dans `config/bundles.php`.

```php
return [
    // …
    ArnaudMoncondhuy\Authorization\AuthorizationBundle::class => ['all' => true],
];
```

## Prise en main

### 1. Déclarer les droits, une énumération par contexte métier

```php
namespace App\Domain\Invoice;

use ArnaudMoncondhuy\Authorization\Permission;

enum InvoicePermission: string implements Permission
{
    case View = 'invoice.view';
    case Finalize = 'invoice.finalize';
    case Backdate = 'invoice.backdate';

    public function id(): string
    {
        return $this->value;
    }
}
```

**Préfixez l'identité par son contexte.** Deux contextes qui choisiraient `view` désigneraient
le même droit, et le partageraient — le paquet refuse de compiler dans ce cas, mais autant ne
pas s'y exposer.

**L'identité est stable une fois écrite.** C'est elle qu'un compte se voit accorder, donc elle
survit en base à la classe qui la déclare : la renommer sans reprendre les droits déjà
accordés les révoque en silence.

### 2. Écrire le cas d'usage

Voir l'exemple en tête. Trois règles de forme :

- `#[RequiresPermission]` au niveau **classe**, autant de fois qu'il exige de droits ;
- une seule méthode publique, `__invoke()` ;
- `$this->access->require(…)` **en tête**, avant toute lecture et toute écriture.

Le troisième point n'est pas cosmétique : un refus qui arrive après une lecture a déjà laissé
fuir ce qu'il refusait.

Le contrat porte une seconde méthode, `can()`, qui **répond au lieu d'arrêter** :

```php
{% if access.can(constant('App\\Domain\\Invoice\\InvoicePermission::Finalize')) %}
```

Elle sert à une surface qui n'affiche que ce qui marchera — un bouton, une entrée de menu, un
outil dans une liste. Elle ne remplace jamais `require()` : ce que la surface cache, le verbe
doit continuer de le refuser, sinon la porte est ouverte à qui devine l'adresse. Un droit
déclaré que le corps se contente de tester est d'ailleurs signalé comme jamais réclamé.

### 3. Écrire le voter — c'est votre part

Le paquet transforme chaque droit en attribut soumis au contrôle d'accès de Symfony. À vous de
dire qui l'obtient.

```php
namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PermissionVoter extends Voter
{
    public function __construct(private readonly GrantRepository $grants)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_contains($attribute, '.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->grants->grants($user, $attribute);
    }
}
```

Le cinquième paramètre, `?Vote $vote = null`, est celui de Symfony 8 : l'omettre fait échouer
la compatibilité de signature.

`supports()` est le seul endroit délicat : il doit reconnaître vos identités **et rien
d'autre**, sans quoi le voter se prononcera sur des attributs qui ne le regardent pas
(`ROLE_USER`, `IS_AUTHENTICATED_FULLY`…). Une convention de préfixe explicite vaut mieux que le
`str_contains` ci-dessus.

**Un voter qui s'abstient faute d'utilisateur ferme le verbe à toute surface sans session** —
console, worker, tâche planifiée. Si vous en avez, préférez refuser explicitement plutôt que
vous abstenir, et voyez la recette « Une surface sans utilisateur ».

### 4. Lister les droits pour les accorder

```php
final class RightsController
{
    public function __invoke(PermissionCatalog $catalog): Response
    {
        // Tous les droits que le code exige, une seule fois chacun, triés par identité.
        $catalog->ids();

        // Un droit stocké que plus aucun cas d'usage n'exige : une case devenue sans effet.
        $catalog->isRequired('invoice.renamed');
    }
}
```

L'inventaire vient des mêmes déclarations que le contrôle : un droit ajouté au code ne peut
pas manquer à la liste, et la liste ne peut pas proposer un droit que rien n'exige.

### 5. Brancher le contrôle qui lit les corps de méthode

```php
use ArnaudMoncondhuy\Authorization\Testing\PermissionUsage;

final class PermissionUsageTest extends TestCase
{
    public function testNoUseCaseBreaksItsContract(): void
    {
        self::assertSame([], PermissionUsage::violationsUnder(__DIR__.'/../src', 'App\\'));
    }
}
```

Il vérifie les deux sens : un droit **déclaré et jamais réclamé** fait apparaître une case que
rien n'applique ; un droit **réclamé et jamais déclaré** n'entre dans aucun inventaire, ne peut
être accordé à personne, et ferme le verbe pour tout le monde — administrateur compris.

Il attrape aussi la brèche signalée plus haut : **une classe qui réclame un droit sans être un
cas d'usage**. Les trois refus de compilation ne la voient pas, puisqu'elle n'implémente pas
`UseCase`.

**La forme qu'il reconnaît, et elle contraint votre écriture :** il cherche
`->require(Enumeration::Case)` **écrit en toutes lettres** dans le corps de `__invoke()`.

```php
$this->access->require(InvoicePermission::Finalize);   // vu

$permission = InvoicePermission::Finalize;
$this->access->require($permission);                   // PAS vu : le droit déclaré
                                                       // sera signalé jamais réclamé
```

Ce n'est pas une négligence : c'est ce qui rend le contrôle sûr. Chercher le nom du droit
plutôt que l'appel laisserait passer un `can()` qui teste sans s'y tenir, puisque les deux
s'écrivent avec les mêmes caractères à un mot près. Écrivez donc le cas en clair — c'est aussi
ce qui rend le corps lisible.

Le corps est lu **débarrassé de ce qui ne s'exécute pas** : commentaires, chaînes littérales,
heredocs. Une garde mise en commentaire le temps d'un débogage n'est donc pas une garde, et
elle est signalée comme telle — sans quoi le contrôle ouvrirait en croyant fermer.

Un droit choisi par une **valeur** est signalé pour ce qu'il est, et non accusé de n'être
jamais réclamé : le geste est bien gouverné, c'est le rapprochement qui devient impossible.

**Ses autres limites, écrites plutôt que tues :** il ne voit pas une énumération importée sous
un autre nom, et **il ne vérifie pas que `require()` vient avant la première lecture**. Un
refus posé après coup a déjà laissé fuir ce qu'il refusait, et aucune lecture de texte ne sait
distinguer une lecture d'un calcul — c'est une règle qui reste à votre charge.

### 6. Examiner l'installation

```bash
php bin/console authorization:permissions   # ce que le code exige
php bin/console authorization:doctor        # ce qui manque pour que ça marche
```

Le docteur cherche ce qu'aucun autre contrôle ne voit : **un droit qu'aucun voter ne prend en
charge**. Le code l'exige, l'inventaire le propose, l'écran d'attribution permet de le cocher —
et il est refusé à tout le monde, administrateur compris, sans qu'aucune erreur ne soit levée.

Il rend un code de sortie, donc une routine qualité peut s'appuyer dessus. Sur une application
sans droit déclaré, il dit qu'il n'y a rien à examiner plutôt que de rendre un vert franc.

Il **compte** les juges plutôt que de s'arrêter au premier : un droit jugé par plusieurs voters
est signalé, parce que sous la stratégie `affirmative` le recouvrement élargit les droits au
lieu de les restreindre. Ce n'est pas toujours une faute, d'où un signalement par défaut et
`--strict` pour en faire un échec.

**Il exécute les voters pour de vrai.** Si l'un des vôtres lit la base, la commande réclame une
base et un schéma à jour — c'est ce qui rend sa réponse fiable, et ce qui la fait échouer
bruyamment avant une migration.

**Sa mesure est indirecte, et il faut le savoir.** Il conclut qu'un droit a un juge si un voter
ne s'abstient pas dessus, interrogé sans utilisateur connecté. Sur un voter bâti sur la classe
abstraite de Symfony, c'est exact — l'abstention y est la réponse quand `supports()` refuse.
Un voter qui s'abstient pour une autre raison, l'absence d'utilisateur par exemple, serait
compté absent à tort.

**Ce qu'il ne voit pas, et que rien ne voit :** une identité **renommée**. Le code compile, les
tests passent, le docteur est vert — et tous ceux qui détenaient l'ancienne l'ont perdue en
silence. C'est la seule chaîne du dispositif qui survive en base à la classe qui la déclare, et
la comparer à ce qui est accordé est un contrôle que seule votre application peut faire, avec
`PermissionCatalog::isRequired()`.

Ce qu'il ne voit pas non plus : un droit jugé par le mauvais modèle. Si l'écran laisse le
cocher sur un rôle quand seul un groupe l'accorde, le droit a bien un juge — il est seulement
accordé au mauvais endroit.

---

## Recettes

Ce que le paquet ne fait pas pour vous, et comment on s'y prend.

### Une surface sans utilisateur — console, worker, tâche planifiée

Un cas d'usage réclame ses droits à l'appelant courant. Une commande n'en a pas : **tous ses
verbes sont refusés** tant qu'on ne lui en donne pas un.

```php
final class ImportCatalogCommand extends Command
{
    public function __construct(
        private readonly SystemIdentity $system,
        private readonly ImportCatalogUseCase $importCatalog,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->system->run($this->identity(), fn () => ($this->importCatalog)());

        return Command::SUCCESS;
    }

    private function identity(): TokenInterface
    {
        // Un rôle qu'aucun compte humain ne porte, et qu'un voter reconnaît.
        $service = new InMemoryUser('import', null, ['ROLE_SERVICE']);

        return new UsernamePasswordToken($service, 'console', $service->getRoles());
    }
}
```

`SystemIdentity` apporte **la portée, jamais l'identité** : le paquet ne sait pas qui vous
autorisez. Le jeton précédent est rendu quoi qu'il arrive, y compris si le traitement lève —
sans quoi une commande qui échoue laisserait ses droits au traitement suivant du même
processus.

**L'`InMemoryUser` ci-dessus ne convient que si vos droits se décident sur les rôles du
jeton.** Dès qu'ils dépendent de l'*objet* utilisateur — appartenance à un groupe, liste
nominative, service d'affectation — un utilisateur en mémoire ne porte rien de tout cela, et
le voter refuse sans que rien n'explique pourquoi. Chargez alors un vrai compte :

```php
private function identity(): TokenInterface
{
    $service = $this->users->loadUserByIdentifier('service');

    return new UsernamePasswordToken($service, 'console', $service->getRoles());
}
```

Le compte de service est un compte comme un autre, sans mot de passe utilisable, et vous
l'inscrivez aux groupes qui lui sont nécessaires — donc visible et modifiable depuis l'écran
d'attribution, comme n'importe quel autre.

Et donner une identité n'accorde rien : c'est toujours un voter qui juge. **Taillez le rôle de
service aussi étroitement qu'un rôle humain** — un rôle qui accorde tout est une porte ouverte,
pas une commodité.

### Mettre en page un refus

Le paquet remplace `MissingPermission` par une `AccessDeniedHttpException` : en production,
Symfony rend alors votre gabarit `error403.html.twig`, et il n'y a rien à écrire.

Pour rendre une réponse à vous — une page qui nomme le droit manquant, une charge JSON pour une
API — posez votre écouteur **devant** celui du paquet, qui est à la priorité par défaut :

```php
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 100)]
final readonly class RefusalListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof MissingPermission) {
            return;
        }

        $event->setResponse(/* votre réponse */);
    }
}
```

Poser une **réponse** plutôt qu'une exception arrête la propagation : le nôtre ne verra rien.

### Des libellés lisibles

`Permission` ne porte qu'une identité — c'est ce qui garde le contrat citable depuis un domaine
pur. Un écran d'attribution a besoin de mots, et la convention qui marche est une clé de
traduction dérivée de l'identité :

```twig
{{ ('permission.' ~ permission.id)|trans }}
```

```yaml
# translations/messages.fr.yaml
permission:
    invoice.finalize: Finaliser une facture
```

Une clé absente s'affiche telle quelle : on voit immédiatement laquelle manque.

### Le tout premier droit

Le paquet garantit qu'aucun verbe ne s'expose sans droit — **y compris celui qui remplit la
table des droits**. Sur une base vierge, personne ne détient rien : le verbe qui installe les
droits se refuse lui-même. C'est une boucle, et elle se pose à la première installation de
toute application.

La sortie n'est pas d'écrire un rôle en dur dans le voter — cela laisserait une porte ouverte
pour toujours. Elle est de faire dépendre ce droit-là de l'état qu'il sert à créer :

```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
{
    // Le seul droit que le modèle n'accorde pas : il s'accorde tant qu'il n'y a
    // rien à accorder, et se referme dès la première ligne posée.
    if (SetupPermission::Install->id() === $attribute) {
        return $this->grants->isEmpty();
    }

    return $this->grants->grants($token->getUser(), $attribute);
}
```

La porte se ferme d'elle-même, sans intervention et sans qu'on ait à y penser. Et le droit
garde un juge, donc `authorization:doctor` reste satisfait.

Reste à décider ce que fait ce verbe si on le rappelle sur une base déjà installée : le plus
sûr est qu'il refuse, plutôt que de remettre les droits à leur état d'usine.

### Faire cohabiter deux modèles de droits

Rien n'empêche que certains droits se décident par rôle et d'autres par groupe. Deux voters
suffisent, à condition que **leurs `supports()` portent sur des identités disjointes**.

**Cette condition n'est pas une propreté, c'est une sécurité.** La stratégie de décision de
Symfony est `affirmative` par défaut : dès qu'**un** voter accorde, l'accès est accordé, et les
refus des autres ne sont pas consultés. Deux voters qui se recouvrent n'aboutissent donc pas au
plus strict des deux mais au plus permissif — un compte sans le moindre rôle obtient le droit
si le voter de trop l'accorde.

Vérifiez la vôtre, elle ne vient pas de ce paquet :

```yaml
# config/packages/security.yaml
security:
    access_decision_manager:
        strategy: affirmative   # le défaut, écrit en toutes lettres plutôt que subi
```

Le moyen sûr n'est pas de relire ses `supports()` de temps en temps, c'est de les **dériver
tous d'une même répartition** — celle qui suit. Deux voters qui lisent la même source ne
peuvent pas se recouvrir.

Le paquet ne dit pas quel modèle gouverne quel droit : c'est une décision d'application. Elle
s'écrit une fois, dans le domaine, et se lit des deux côtés — par les voters et par l'écran
d'attribution :

```php
enum GrantModel
{
    case ByRole;
    case ByGroup;

    public static function of(string $permission): self
    {
        return str_starts_with($permission, 'invoice.') ? self::ByGroup : self::ByRole;
    }
}
```

Sans cette répartition partagée, l'écran laisse cocher un droit sur un rôle qui ne l'accordera
jamais. `authorization:doctor` ne voit pas cette faute-là : le droit a bien un juge, c'est
seulement qu'on l'accorde au mauvais endroit.

## Ce qui arrive en cas de refus

`Authorizer::require()` lève `MissingPermission`, une exception du **métier** — un cas d'usage
se joue aussi hors HTTP. Elle porte le droit manquant, pour qu'une surface puisse dire lequel.

En présence de `symfony/http-kernel`, le paquet enregistre un écouteur qui la traduit en 403.
Pour la traduire autrement — une erreur nommée pour un outil, un message pour une console —
attrapez-la vous-même :

```php
try {
    ($this->finalizeInvoice)($number);
} catch (MissingPermission $refusal) {
    return new ToolError(\sprintf('Droit manquant : %s', $refusal->permission->id()));
}
```

## Dépendances

| Composant | Pourquoi |
|---|---|
| `symfony/dependency-injection` | les trois passes, et la classe de bundle |
| `symfony/config` | le chargement de la configuration du bundle |
| `symfony/security-core` | l'adaptateur qui soumet l'identité au contrôle d'accès |
| `symfony/event-dispatcher` | l'écouteur qui traduit un refus |
| `symfony/http-kernel` | *suggéré* — sans lui, l'écouteur n'est pas enregistré |
| `symfony/console` | *suggéré* — sans lui, les deux commandes n'existent pas |

Le **contrat** — `Permission`, `UseCase`, `RequiresPermission`, `Authorizer`,
`MissingPermission`, `PermissionCatalog` — est du PHP nu, sans une seule dépendance. C'est ce
qui permet de le citer depuis un domaine pur. La routine qualité le vérifie à chaque
exécution, imports et noms qualifiés compris.

## Ce qui reste au projet

Le paquet livre le mécanisme, jamais les garanties d'ensemble. Ce qui suit ne peut pas voyager,
vous appartient, et **rien du paquet ne vous préviendra**. La liste vient d'un audit mené sur
une application réelle bâtie dessus : chaque règle a été enfreinte pour de bon, et chaque
formulation est faite pour être vérifiable à la lecture ou au `grep`.

**1. Réclamer le droit là où la demande est acceptée, pas seulement là où elle est traitée.**
Une surface qui poste un message au lieu d'appeler un cas d'usage ne traverse aucun
`require()`. Le paquet n'a rien vu passer et ne le signalera pas.
*Vérifiable :* toute route qui appelle `dispatch()` appelle d'abord `require()`.

**2. L'identité qui traverse une file vient du jeton de sécurité, jamais de la requête.** Un
identifiant lu dans un corps JSON est un droit d'emprunt offert au client — y compris sur vos
comptes de service, ceux qu'aucune connexion humaine n'atteint.
*Vérifiable :* aucun champ d'un message n'est alimenté depuis la requête.

**3. Une identité de service se construit dans l'application.** `SystemIdentity::run()` accepte
le jeton qu'on lui donne et ne demandera **jamais** d'où il vient : c'est son rôle, et c'est
aussi par là qu'on se fait emprunter une identité.
*Vérifiable :* tout appel construit son jeton depuis une valeur d'origine interne.

**4. Le paquet ne connaît pas d'objet.** `can()` et `require()` prennent une `Permission` et
rien d'autre : « puis-je agir sur *cette* facture-ci » ne peut pas leur être demandé.
L'autorisation d'objet est entièrement à vous, **dans le cas d'usage, après le contrôle du
verbe et avant toute lecture**.
*Vérifiable :* tout cas d'usage qui reçoit un identifiant d'objet porte ce second contrôle.

**5. Si vous posez des regroupements de droits, l'écran qui accorde déplie ce que le voter
déplie.** Sinon l'écran affiche un état que la réalité contredit, et l'erreur va dans le sens
permissif : des cases décochées sont pourtant accordées.
*Vérifiable :* le calcul du « coché » et le voter passent par la même fonction de dépliage.

**6. Garder l'invariant du dernier titulaire.** Le paquet laissera vider le rôle qui accorde.
Un formulaire de cases à cocher renvoyé vide révoque tout, et referme l'application sur
elle-même sans retour possible.
*Vérifiable :* le verbe qui fixe les droits examine l'état résultant, pas chaque ligne postée.

**7. Choisir, et écrire, ce qu'un refus révèle.** `MissingPermission` porte l'identité du
droit ; la rendre au client donne à un compte sans droit l'inventaire de vos permissions. C'est
acceptable, à condition d'être décidé.
*Vérifiable :* chaque écouteur de refus dit ce qu'il divulgue, et pourquoi.

**8. Traduire aussi ce qui n'est pas un refus.** Un écouteur qui ne connaît que
`MissingPermission` laisse une exception métier rendre une page de débogage à un client JSON.
*Vérifiable :* chaque surface a une réponse pour `\Throwable`, pas seulement pour le refus.

**9. L'authentification est hors du paquet, en entier.** Limitation des essais, uniformité du
temps de réponse entre compte connu et inconnu — un compte sans mot de passe répond cinq fois
plus vite —, durée de vie des jetons d'API, jeton de formulaire sur la déconnexion : rien de
tout cela n'est couvert, et rien ne le signalera.

**10. Une application de développement joignable ailleurs qu'en local publie ses secrets.** Le
profileur rend la clé d'application, les identifiants de base, les jetons et les sessions à qui
les demande. `authorization:doctor` ne dira rien de votre environnement.

Et trois règles d'architecture, si votre projet en tient une : **empêcher une surface
d'atteindre la base sans passer par un cas d'usage** ; **couper la résolution implicite
d'entités depuis les arguments de contrôleur** (`doctrine.orm.controller_resolver.enabled:
false`), qui charge une table sans qu'aucun droit ne soit réclamé ; **surveiller
`autoconfigure: false`**, seule façon de soustraire un service au tag posé par le bundle, donc
aux trois contrôles.

## Version

`0.x` : aucune promesse de compatibilité. La surface publique peut bouger d'une version mineure
à l'autre tant que `1.0` n'est pas sorti.

## Licence

MIT.
