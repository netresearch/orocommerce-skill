# OroCommerce Entity Ownership Types — Complete Reference

## Quick Decision Matrix

| Ownership Type | Access Scope | Multi-Org | Multi-User | Example | Organization Field | Owner Field |
|---|---|---|---|---|---|---|
| **GLOBAL** | System-wide | No | No | Settings, product taxonomies | No | No |
| **ORGANIZATION** | Single organization | Yes | Shared | Shared catalogs, contracts | **Required** | No |
| **BUSINESS_UNIT** | Department/team | Yes | Shared by BU | Sales queue, support tickets | **Required** | No* |
| **USER** | Personal/assigned | Yes | One per record | Tasks, notes, draft orders | **Required** | **Required** |

*BUSINESS_UNIT can have an optional owner, but organization is mandatory.

---

## Ownership Type Details

### GLOBAL
System-wide scope. No ownership tracking.

**When to use:**
- Reference data (product types, statuses, enumerations)
- System settings and configuration
- Global master data (currencies, units of measure)
- Shared taxonomies across all organizations

**Configuration:**
```php
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'GLOBAL',
        ],
    ]
)]
class ProductType
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string')]
    private $name;
}
```

**Database schema:**
```php
$table->addColumn('id', 'integer', ['autoincrement' => true]);
$table->addColumn('name', 'string', ['length' => 255]);
// No organization_id, no owner_id
```

**Access control:**
- All users in all organizations can read
- Only admin users can modify
- No row-level filtering

**Queries:**
```php
// Simple, no filtering needed
$products = $this->entityManager
    ->getRepository(ProductType::class)
    ->findAll();
```

---

### ORGANIZATION
Multi-organization scope. Shared within an organization but isolated between them.

**When to use:**
- Shared resources across departments within an organization
- Organization-level catalogs or configurations
- Shared documents, templates, or policies
- Contracts, agreements that apply org-wide

**Configuration:**
```php
use Oro\Bundle\OrganizationBundle\Entity\Organization;

#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_catalog')]
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'ORGANIZATION',
            'owner_field_name' => 'organization',
            'owner_column_name' => 'organization_id',
        ],
    ]
)]
class Catalog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string')]
    private $name;

    // MANDATORY field
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private $organization;

    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
    }
}
```

**Database schema:**
```php
$table->addColumn('id', 'integer', ['autoincrement' => true]);
$table->addColumn('name', 'string', ['length' => 255]);
$table->addColumn('organization_id', 'integer', ['notnull' => false]);
$table->addIndex(['organization_id'], 'idx_org');
```

**Access control:**
- Users see only catalogs in their organization
- Organization admins can modify catalogs
- Row filtering by organization applied automatically

**Queries:**
```php
// Oro's ACL automatically filters by current organization
$catalogs = $this->entityManager
    ->getRepository(Catalog::class)
    ->findAll();  // Only current org's catalogs returned

// Explicit filtering (rare)
$catalogs = $this->entityManager
    ->getRepository(Catalog::class)
    ->findBy(['organization' => $organizationId]);
```

---

### BUSINESS_UNIT
Department/team scope. Implies multi-organization.

**When to use:**
- Department-owned resources (sales queue, support tickets)
- Team-specific workflows
- Hierarchical access (parent BU inherits child BU records)
- Role-based resource grouping

**Configuration:**
```php
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;

#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_ticket')]
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'BUSINESS_UNIT',
            'owner_field_name' => 'businessUnit',
            'owner_column_name' => 'business_unit_id',
        ],
    ]
)]
class SupportTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string')]
    private $subject;

    // MANDATORY: Organization field
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private $organization;

    // MANDATORY: Business Unit field (specifies the owner department)
    #[ORM\ManyToOne(targetEntity: BusinessUnit::class)]
    #[ORM\JoinColumn(name: 'business_unit_id', nullable: false)]
    private $businessUnit;

    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function setBusinessUnit(BusinessUnit $businessUnit): void
    {
        $this->businessUnit = $businessUnit;
    }
}
```

**Database schema:**
```php
$table->addColumn('id', 'integer', ['autoincrement' => true]);
$table->addColumn('subject', 'string', ['length' => 255]);
$table->addColumn('organization_id', 'integer', ['notnull' => false]);
$table->addColumn('business_unit_id', 'integer', ['notnull' => false]);
$table->addIndex(['organization_id'], 'idx_ticket_org');
$table->addIndex(['business_unit_id'], 'idx_ticket_bu');
```

**Access control:**
- Users see tickets assigned to their business unit
- Parent business units can see child business unit tickets (hierarchical)
- Business unit heads/admins can reassign between BUs
- Row filtering by BU applied automatically

**Queries:**
```php
// ACL filters by current user's business unit (and parents)
$tickets = $this->entityManager
    ->getRepository(SupportTicket::class)
    ->findAll();  // Only current BU + parent BUs' tickets returned

// Explicit filtering (for override)
$tickets = $this->entityManager
    ->getRepository(SupportTicket::class)
    ->findBy(['businessUnit' => $businessUnitId]);
```

---

### USER
Personal/individual ownership. Implies multi-organization.

**When to use:**
- Personal tasks and reminders
- Draft/unpublished work (draft orders, draft documents)
- Individual assignments (support tickets assigned to a person)
- User-specific preferences or settings

**Configuration:**
```php
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;

#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_task')]
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'owner_id',
        ],
    ]
)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string')]
    private $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    // MANDATORY: Organization field (user must be in this org)
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private $organization;

    // MANDATORY: Owner field (the user who owns the task)
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false)]
    private $owner;

    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function setOwner(User $owner): void
    {
        $this->owner = $owner;
    }
}
```

**Database schema:**
```php
$table->addColumn('id', 'integer', ['autoincrement' => true]);
$table->addColumn('title', 'string', ['length' => 255]);
$table->addColumn('description', 'text', ['notnull' => false]);
$table->addColumn('organization_id', 'integer', ['notnull' => false]);
$table->addColumn('owner_id', 'integer', ['notnull' => false]);
$table->addIndex(['organization_id'], 'idx_task_org');
$table->addIndex(['owner_id'], 'idx_task_owner');
```

**Access control:**
- Users see only their own tasks
- Managers with appropriate permission can see team member tasks
- Row filtering by owner applied automatically
- Owner can delete or transfer task

**Queries:**
```php
// ACL filters by current user (or team if manager has permission)
$myTasks = $this->entityManager
    ->getRepository(Task::class)
    ->findAll();  // Only current user's tasks returned

// Explicit filtering (for override)
$tasks = $this->entityManager
    ->getRepository(Task::class)
    ->findBy(['owner' => $userId]);

// Find by current user
$currentUser = $this->tokenStorage->getToken()->getUser();
$myTasks = $this->entityManager
    ->getRepository(Task::class)
    ->findBy(['owner' => $currentUser]);
```

---

## Hybrid Pattern: USER with Optional BUSINESS_UNIT

Some entities support **both** USER ownership with an **optional** BUSINESS_UNIT context:

```php
#[ORM\Entity]
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'owner_id',
        ],
    ]
)]
class DraftOrder
{
    #[ORM\Column(type: 'integer')]
    private $id;

    // MANDATORY
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private $organization;

    // MANDATORY
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false)]
    private $owner;

    // OPTIONAL: For tracking which department created the draft
    #[ORM\ManyToOne(targetEntity: BusinessUnit::class)]
    #[ORM\JoinColumn(name: 'business_unit_id', nullable: true)]
    private $businessUnit;
}
```

Use this pattern when:
- Record is personally owned but linked to a department for audit/tracking
- Department managers need visibility into team member's drafts

---

## Migration Example: Adding Ownership to Existing Entity

```php
<?php
namespace Acme\Bundle\DemoBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddOwnershipToDocument implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('acme_demo_document');

        // Add organization column
        $table->addColumn(
            'organization_id',
            'integer',
            ['notnull' => false]
        );

        // Add owner column
        $table->addColumn(
            'owner_id',
            'integer',
            ['notnull' => false]
        );

        // Add indexes for performance
        $table->addIndex(['organization_id'], 'idx_document_org');
        $table->addIndex(['owner_id'], 'idx_document_owner');

        // Foreign key constraints (optional but recommended)
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );

        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['owner_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
    }
}
```

Then backfill existing records:

```php
// In a separate data migration (v1_1 directory)
public function up(Schema $schema, QueryBag $queries): void
{
    // Assign all existing documents to the default org and a default user
    $queries->addQuery(
        'UPDATE acme_demo_document SET organization_id = 1, owner_id = 1'
    );
}
```

---

## Testing Ownership Logic

```php
class DocumentRepositoryTest extends TestCase
{
    public function testFindByOrganization()
    {
        $org1 = $this->createOrganization('Org 1');
        $org2 = $this->createOrganization('Org 2');

        $doc1 = new Document();
        $doc1->setOrganization($org1);
        $doc1->setOwner($this->getTestUser());
        $this->em->persist($doc1);

        $doc2 = new Document();
        $doc2->setOrganization($org2);
        $doc2->setOwner($this->getTestUser());
        $this->em->persist($doc2);

        $this->em->flush();

        // Verify organization filter works
        $this->em->getUnitOfWork()->clear();
        // Set current organization context to org1
        // Query should return only doc1
    }
}
```

---

## Common Mistakes

1. **Missing organization field on USER/BUSINESS_UNIT entities**
   - Results in `NOT NULL constraint failed` on insert
   - Always include organization

2. **Using nullable=true on ownership fields**
   - Violates data integrity
   - Keep ownership fields `nullable: false`

3. **Forgetting to set ownership fields when creating records**
   - Records become orphaned
   - Set organization/owner in controller/service constructor

4. **Assigning wrong ownership type to entity**
   - If you need department scoping, use BUSINESS_UNIT, not USER
   - If you need personal ownership, use USER, not ORGANIZATION
   - Wrong type = access control failures

5. **Not updating #[Config] attribute after database changes**
   - Entity cache won't reflect new ownership configuration
   - Always run `oro:entity-extend:cache:clear` after changes
