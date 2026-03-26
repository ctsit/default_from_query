# Default From Query

[![DOI](https://zenodo.org/badge/1189692257.svg)](https://doi.org/10.5281/zenodo.19225884)

Default From Query is a REDCap External Module that allows REDCap fields to be auto-populated with values derived from SQL queries against REDCap's own database. Queries are defined by a system administrator at the project level and referenced by name from a field's action tag.

## Prerequisites

- REDCap >= 14.6.4
- PHP >= 8.2.0

## Installation

- Obtain this module from the Consortium [REDCap Repo](https://redcap.vumc.edu/consortium/modules/index.php) from the control center.
- Go to **Control Center > Manage External Modules** and enable Default From Query.

- Enable the module for each project that needs it via the project's **Manage External Modules** page.

## Usage

### 1. Enable the module on the project

Enable the module for the project via the project's **Manage External Modules** page.

### 2. Define a query

In the project's External Modules configuration for Default From Query, add one or more query entries. Each entry requires:

| Field | Who can set it | Description |
|-------|---------------|-------------|
| **Query Name** | Any project administrator | A short identifier used to reference this query in action tags |
| **SQL** | REDCap Admin only | A SQL statement that returns a single scalar value |

### 3. Reference the query by name in a field's action tag

In the Online Designer, add the following action tag to any field you want auto-populated:

```
@DEFAULT-FROM-QUERY='query_name'
```

where `query_name` matches the **Query Name** configured in project settings. When a user opens a data entry form for a record that has no existing data on that form, the module executes the associated query and injects the result as the field's default value.

### Variable substitution in SQL

Queries may reference the following **Smart Variables**, which are substituted with the current context before execution:

| Placeholder | Substituted with |
|-------------|-----------------|
| `[project-id]` | The current project ID |
| `[field-name]` | The name of the field being populated |
| `[record-name]` | The current record ID (Note: For yet unsaved records, the record ID will be `null`) |
| `[record-dag-id]` | The data access group id of the record |
| `[event-id]` | The current event id |
| `[current-instance]` | The current instance number |
| `[data-table]` | The REDCap data table for the current project (e.g. `redcap_data`). Data tables for other projects may be referenced by using the syntax `[data-table:pid]` where `pid` is a literal project ID or `pid-1`, `pid-2`, or `pid-3`, referencing the 1st, 2nd, or 3rd additional project ID set in the module settings for the query |
| `[pid-1]`, `[pid-2]`, `[pid-3]` | The project ID configured in the corresponding additional project ID field |

Example:

```sql
SELECT value
FROM [data-table]
WHERE project_id = [project-id]
  AND record = [record-name]
  AND field_name = [field-name]
ORDER BY instance DESC
LIMIT 1
```

#### Caveats

- Do not enclose any of these substitions with quotes or the query will fail and return to no value.

```sql
SELECT value
FROM [data-table]
WHERE project_id = [project-id]
  AND record = '[record-name]' -- this will fail!
  AND field_name = '[field-name]' -- this will also fail!
ORDER BY instance DESC
LIMIT 1
```

- Values for `record` and `field_name` _must_ be wrapped in quotes if you are _not_ using the substition value for that column value.

```sql
SELECT value
FROM [data-table]
WHERE project_id = [project-id]
  AND record = [record-name]
  AND field_name = 'some_other_field' -- Quotes are required in this context
ORDER BY instance DESC
LIMIT 1
```

- If your query needs to query multiple project ids, record ids or field names, you will need to manage those differences as you write the query. The substition values will always be in the context of the project, field, and record of the form as it is opened on the data entry page. References to up to three other projects may be specified via settings, but any further project ids or other field names will need to be hard-coded in the query. 

```sql
select coalesce(max(max_visitnum) + 1, 1) as next_visitnum
from (
        (
            select max(cast(value as SIGNED)) as max_visitnum
            from [data-table]
            where project_id = [project-id]
                and field_name = [field-name]
                and record = [record-name] -- when you need only the value provided by the substitution, you can use it.
        )
        union
        (
            select max(cast(value as SIGNED)) as max_visitnum
            from [data-table:pid-1] -- the data tables do not match so use a placeholder (set in module settings; alternatively, hard-code as, e.g., [data-table:123])
            where project_id = [pid-1] -- the project_ids do not match so use a placeholder (set in module settings; alternatively, hard-code the project id)
                and field_name = 'some_other_field'
                and record = [record-name] --- the record name matches the one in the current project
        )
    ) as dummy;
```

#### Draft Preview Mode

This module supports draft preview mode, but data retrieval through SQL queries is of course limited to values that are actually stored in the data table.

### Testing

A prebuilt test project, SQL queries, and a test procedure are available in [Testing Default From Query](./testing.md)

## Upgrading

### Breaking changes in v1.0.0

The smart variable placeholders used in SQL queries were renamed in v1.0.0. If you have existing queries using the old names, update them before upgrading:

| Old placeholder | New placeholder |
|-----------------|-----------------|
| `[project_id]`  | `[project-id]`  |
| `[record_id]`   | `[record-name]` |
| `[field_name]`  | `[field-name]`  |
| `[pid1]`        | `[pid-1]`       |
| `[pid2]`        | `[pid-2]`       |
| `[pid3]`        | `[pid-3]`       |
| `[data-table:pid1]` | `[data-table:pid-1]` |
| `[data-table:pid2]` | `[data-table:pid-2]` |
| `[data-table:pid3]` | `[data-table:pid-3]` |

Queries using old placeholder names will silently produce no value after upgrading.

## License

Apache 2.0 — see [LICENSE](LICENSE).
