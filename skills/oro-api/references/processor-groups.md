# OroCommerce v6.1 API Processor Groups Reference

## Processor Group Execution Order

API processors execute in a strict pipeline. Each group runs all processors before the next group begins.

### 1. initialize
**Purpose:** Setup request context, validate route parameters, initialize data structures.

**When to hook:**
- Setting up shared data in context
- Validating entity exists before processing
- Initializing custom state

**Example:**
```yaml
tags:
    - { name: oro.api.processor, action: get, group: initialize, priority: 10 }
```

**Available context:** Request method, entity name, resource class.

---

### 2. resource_check
**Purpose:** Verify the requested resource (entity/relationship) exists and is valid.

**When to hook:**
- Rarely needed; core processors handle this
- Custom resource type validation

**Example:**
```yaml
tags:
    - { name: oro.api.processor, action: get, group: resource_check, priority: 10 }
```

---

### 3. normalize_input
**Purpose:** Parse and normalize client input (POST/PATCH bodies, query parameters).

**When to hook:**
- Custom input parsing before validation
- Field value transformations (e.g., string to date)
- Default value injection

**Example:**
```php
public function process(Context $context) {
    $data = $context->getRequestData();
    if (isset($data['date_string'])) {
        $data['date'] = \DateTime::createFromFormat('Y-m-d', $data['date_string']);
        $context->setRequestData($data);
    }
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: create, group: normalize_input, priority: 10 }
```

---

### 4. security_check
**Purpose:** Check user permissions (ACL, roles) for the action.

**When to hook:**
- Custom permission logic beyond ACL
- Resource-level authorization (not implemented by default, use here)
- Audit logging for permission checks

**Example:**
```php
public function process(Context $context) {
    // Custom authorization logic
    if (!$this->authorizationChecker->isGranted('EDIT', $resource)) {
        $context->addError(new Error('Forbidden', '403'));
    }
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: update, group: security_check, priority: 10 }
```

---

### 5. load_data
**Purpose:** Fetch entity data from database.

**When to hook:**
- Rarely; core Doctrine processor handles this
- Custom datasources (non-Doctrine)
- Prefetching related entities

**Example:**
```php
public function process(Context $context) {
    $entityId = $context->getId();
    $entity = $this->repository->find($entityId);
    if ($entity) {
        $context->setResult($entity);
    }
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: get, group: load_data, priority: 10 }
```

---

### 6. data_security_check
**Purpose:** Check field-level security (which fields user can access).

**When to hook:**
- Custom field-level authorization
- Sensitivity-based field filtering
- Cross-tenant data isolation

**Example:**
```php
public function process(Context $context) {
    $config = $context->getConfig();
    foreach ($config->getFields() as $fieldName => $fieldConfig) {
        if (!$this->fieldSecurityProvider->hasAccess($fieldName)) {
            $fieldConfig->setExcluded(true);
        }
    }
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: get, group: data_security_check, priority: 10 }
```

---

### 7. transform_data
**Purpose:** Convert entity data to API format (JSON:API serialization, transformations).

**When to hook:**
- Custom field formatting (e.g., number formatting)
- Denormalization (flatten nested objects)
- Data aggregation (computed fields)

**Example:**
```php
public function process(Context $context) {
    $data = $context->getResult();
    if (is_array($data) && isset($data['amount'])) {
        $data['amount_formatted'] = number_format($data['amount'], 2, '.', ',');
        $context->setResult($data);
    }
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: get, group: transform_data, priority: 10 }
```

---

### 8. save_data
**Purpose:** Persist entity changes to database (for create/update/delete).

**When to hook:**
- Rarely; Doctrine processor handles persistence
- Custom persistence logic (non-database backends)
- Pre-commit validation

**Example:**
```php
public function process(Context $context) {
    $entity = $context->getResult();
    if ($entity) {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: create, group: save_data, priority: 10 }
```

---

### 9. normalize_data
**Purpose:** Final data transformations and normalization before output.

**When to hook (most common):**
- Add computed/virtual fields
- Enrich response with additional data
- Format output for client expectations
- Attach relationship counts or summaries

**Example:**
```php
public function process(Context $context) {
    $data = $context->getResult();
    if (is_array($data)) {
        $data['comment_count'] = count($data['comments'] ?? []);
        $data['status_label'] = $this->getStatusLabel($data['status']);
        $context->setResult($data);
    }
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: get, group: normalize_data, priority: 10 }
```

**Most processors you write will be in this group.**

---

### 10. finalize
**Purpose:** Final cleanup, logging, response finalization.

**When to hook:**
- Logging/auditing
- Cache invalidation
- Cleanup resources
- Adding response headers

**Example:**
```php
public function process(Context $context) {
    $this->logger->info('API response generated', [
        'action' => $context->getAction(),
        'entity' => $context->getClassName(),
    ]);
}
```

**Tag:**
```yaml
tags:
    - { name: oro.api.processor, action: get, group: finalize, priority: 10 }
```

---

## Action-Specific Group Availability

Not all groups apply to every action. Common combinations:

### GET (retrieve single)
- initialize → resource_check → security_check → load_data → data_security_check → transform_data → normalize_data → finalize

### GET_LIST (list with filters/sorting)
- initialize → security_check → load_data → transform_data → normalize_data → finalize
- (no resource_check; no data_security_check for list context)

### CREATE (POST)
- initialize → security_check → normalize_input → load_data → transform_data → save_data → normalize_data → finalize

### UPDATE (PATCH)
- initialize → resource_check → security_check → normalize_input → load_data → transform_data → save_data → normalize_data → finalize

### DELETE
- initialize → resource_check → security_check → load_data → save_data → finalize

---

## Priority Reference

Processor execution order within a group:

```yaml
# Higher priority runs FIRST
- { name: oro.api.processor, group: normalize_data, priority: 100 }  # Runs first
- { name: oro.api.processor, group: normalize_data, priority: 50 }   # Runs second
- { name: oro.api.processor, group: normalize_data, priority: 10 }   # Runs third
- { name: oro.api.processor, group: normalize_data, priority: 0 }    # Runs last
```

**Default core priorities:**
- 255 — Pre-processing (early setup)
- 128 — Main logic
- 64 — Post-processing (cleanup)
- 0 — Final catch-all

Set your processor priority based on dependencies:
- **High (100+):** Runs before most core logic; useful for input validation
- **Medium (50):** Runs alongside core logic
- **Low (10):** Runs after core; safe for enhancement/enrichment
- **Very Low (0):** Runs last; good for cleanup/logging

---

## Common Processor Patterns (v6.1)

### Pattern 1: Add Computed Field (normalize_data, priority: 10)
```php
public function process(Context $context) {
    $data = $context->getResult();
    if (is_array($data)) {
        $data['full_name'] = $data['first_name'] . ' ' . $data['last_name'];
        $context->setResult($data);
    }
}
```

### Pattern 2: Enrich Response with Related Data (normalize_data, priority: 10)
```php
public function process(Context $context) {
    $entity = $context->getResult();
    if ($entity instanceof Document) {
        $commentCount = $this->commentRepo->countByDocument($entity->getId());
        $data = $context->getResult();
        $data['comment_count'] = $commentCount;
        $context->setResult($data);
    }
}
```

### Pattern 3: Custom Input Validation (normalize_input, priority: 100)
```php
public function process(Context $context) {
    $data = $context->getRequestData();
    if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $context->addError(new Error('Invalid email format', '400'));
    }
}
```

### Pattern 4: Object-Level Authorization (data_security_check, priority: 100)
```php
// Use data_security_check, NOT security_check — the entity is only
// available after load_data has run. security_check is for type-level
// (class) ACL checks only.
public function process(ContextInterface $context): void {
    $entity = $context->getResult();
    if (!$entity instanceof Document) {
        return;
    }
    if ($entity->getOwner()?->getId() !== $this->tokenAccessor->getUserId()) {
        throw new AccessDeniedException('Access denied');
    }
}
```
Service tag: `{ name: oro.api.processor, action: get, group: data_security_check, priority: 100 }`

---

## Processor Context Methods

Common context methods available in all processors:

```php
// Getters
$action = $context->getAction();              // 'get', 'create', 'update', etc.
$className = $context->getClassName();        // Full entity class path
$config = $context->getConfig();              // API config object
$entity = $context->getResult();              // Entity (only after load_data group)
$data = $context->getRequestData();           // Client input (POST/PATCH body)
$id = $context->getId();                      // Entity ID for get/update/delete

// Setters
$context->setResult($entity);                 // Update entity
$context->setRequestData($data);              // Update input
$context->set('key', $value);                 // Store arbitrary data

// Error handling
$context->addError(new Error('msg', '400')); // Add error
$context->hasErrors();                        // Check for errors
```

---

## Debugging Processors

Enable API processor logging:

```yaml
# config/packages/dev/monolog.yml
monolog:
    channels:
        - oro_api
    loggers:
        oro.api:
            level: debug
            channels: [oro_api]
```

Check logs:
```bash
tail -f var/logs/dev.log | grep "oro.api"
```

Inspect processor execution:
```bash
php bin/console debug:container --tag=oro.api.processor
```

Lists all registered processors with groups and priorities.
