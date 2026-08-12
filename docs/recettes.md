# Recettes

Ce que le paquet ne fait pas pour vous, et comment on s'y prend. Chaque recette répond à une
question, et vient d'une application réelle qui l'a posée.

Ce que le paquet ne fait pas pour vous, et comment on s'y prend.

## Une surface sans utilisateur — console, worker, tâche planifiée

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

## Mettre en page un refus

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

## Des libellés lisibles

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

## Le tout premier droit

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

## Une porte qui n'appelle aucun verbe

Le quatrième refus de compilation examine ce que Symfony marque lui-même comme porte d'entrée :
`controller.service_arguments`, `console.command`, `messenger.message_handler`. **Rien à
déclarer** — un contrôleur ajouté demain est examiné parce que le framework le marque, et une
liste qu'on oublie de tenir laisserait passer exactement ce que ce contrôle cherche.

Une porte qui n'appelle légitimement aucun verbe — une redirection, un formulaire de connexion
— le dit sur place, avec sa raison :

```php
#[CallsNoUseCase('Redirige vers le tableau de bord, sans lire aucune donnée.')]
final class HomeController
```

Une application qui ouvre une porte d'un autre genre — un outil pour un assistant, un point
d'entrée JSON-RPC — la fait examiner en nommant son tag :

```yaml
parameters:
    authorization.surface_tags: ['app.mcp_tool']
```

**Sa limite, écrite plutôt que tue :** une porte livrée par une dépendance n'est pas la vôtre à
gouverner, et se reconnaît à son chemin de fichier — un projet qui aurait renommé son dossier
`vendor/` y échapperait.

## Ce qu'un autre que l'appelant a le droit de faire

`Authorizer` répond toujours pour l'appelant courant, et c'est ce qui le rend sûr. Trois
questions n'entrent pas dans cette forme : ne pas notifier quelqu'un sur un module qu'il a
perdu, montrer à un administrateur ce qu'une personne obtiendra, vérifier avant d'accorder ce
qu'un compte détient déjà.

```php
public function __construct(private UserAuthorizer $rights)
{
}

if (!$this->rights->can($destinataire, InvoicePermission::View)) {
    return;   // ne pas notifier sur ce qu'il ne peut plus voir
}
```

Le compte est désigné par son identifiant, chargé par **votre** fournisseur, et jugé par **vos**
voters : la décision reste écrite à un seul endroit. Sans ce contrat, la seule issue est de
réimplémenter la décision hors session — et deux copies d'une même règle finissent par diverger.

**Il ne sait que répondre.** Aucune méthode ne lève, et il n'existe pas de pendant à
`require()` : savoir ce qu'un tiers peut faire n'est pas l'autoriser à le faire, et confondre
les deux transformerait une lecture en usurpation. Un identifiant qu'aucun compte ne porte rend
`false`.

## L'objet interdit

Le contrat n'a pas de sujet. « Puis-je modifier *cette* facture-ci » ne se demande donc pas à
`require()`, et il y a deux façons de le tenir. Elles ne s'excluent pas.

**Contrôler dans le verbe, après le droit et avant toute lecture.**

```php
public function __invoke(string $number): void
{
    $this->access->require(InvoicePermission::Edit);

    $invoice = $this->invoices->byNumber($number) ?? throw new UnknownInvoice($number);

    if (!$invoice->belongsTo($this->caller->identity())) {
        throw new UnknownInvoice($number);   // et non « interdit » : voir plus bas
    }
}
```

**Ou borner la lecture en amont, pour que l'objet interdit n'arrive jamais au verbe.** Un
filtre Doctrine (`SQLFilter`) armé sur la requête retire de la base ce que l'appelant n'a pas
le droit de voir : le dépôt ne le rend plus, et la question ne se pose plus.

C'est souvent la meilleure des deux, et pour une raison qui n'a rien de théorique : **elle tient
aussi les listes**. Un contrôle dans le verbe doit être rappelé sur chaque élément d'une
collection, et une pagination appliquée avant le filtrage rend des pages de tailles inégales.

Trois choses à savoir avant de la choisir :

- **Elle ne couvre que la lecture.** Voir une facture et pouvoir la modifier restent deux
  questions ; le filtre ne répond qu'à la première. Le jour où « modifier seulement les
  miennes » est demandé, il faut le contrôle dans le verbe malgré tout.
- **Un objet filtré fait lever les proxies paresseux.** Une association vers une ligne que le
  filtre masque rend `EntityNotFoundException` — donc une erreur serveur, pas un refus. Les
  entités filles doivent hériter du filtre par une jointure, jamais par une copie du niveau
  de visibilité, qu'une propagation oubliée désynchroniserait en silence.
- **Elle change le refus en absence.** Une fiche invisible n'est pas interdite, elle n'existe
  pas : adresse directe, `404`. C'est **plus sûr** — un `403` révèle que la fiche existe — mais
  c'est une décision à prendre en connaissance de cause, et à rendre partout de la même façon.

## Un droit qui dépend de l'objet, dans un gabarit

`can()` prend une `Permission` et rien d'autre : `{% if can(...) %}` ne sait pas parler d'une
facture précise. Un bouton dont l'affichage dépend de l'objet continue donc d'appeler le
contrôle d'accès du framework directement :

```twig
{% if is_granted('INVOICE_EDIT', invoice) %}
```

**C'est une frontière, pas un manque.** Ces droits-là relèvent d'un voter Symfony ordinaire,
avec un sujet, et n'entrent pas dans l'inventaire — puisque l'inventaire recense ce que le code
*exige*, et qu'aucun cas d'usage n'exige « modifier cette facture-ci ». Deux vocabulaires
cohabitent alors dans les gabarits, et c'est normal : l'un dit le verbe, l'autre dit l'objet.

Ce que la surface cache reste refusé par le verbe. Une case masquée n'est pas un contrôle.

## Faire cohabiter deux modèles de droits

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
