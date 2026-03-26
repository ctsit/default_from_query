# default_from_query 2.0.0 (released 2026-03-26)

## Breaking changes
- Rename smart variable placeholders to use hyphens instead of underscores: `[project_id]` → `[project-id]`, `[record_id]` → `[record-name]`, `[field_name]` → `[field-name]`, `[pid1]`/`[pid2]`/`[pid3]` → `[pid-1]`/`[pid-2]`/`[pid-3]`, `[data-table:pid1]`/`[data-table:pid2]`/`[data-table:pid3]` → `[data-table:pid-1]`/`[data-table:pid-2]`/`[data-table:pid-3]` (@pbchase, @grezniczek)

## Enhancements
- Add read-only scanner to detect and reject queries that attempt to write to the database (@grezniczek)
- Add smart variable placeholders for `[record-dag-id]`, `[event-id]`, and `[current-instance]` (@grezniczek)
- Always issue rollback after query execution (@grezniczek)

## Bug fixes
- Fix pid key mismatch so `[pid-1]`/`[pid-2]`/`[pid-3]` substitution works (@pbchase)
- Replace `$_GET` usage in `currentFormHasData` with hook parameters (@pbchase)
- Fix stale docblock, inconsistent `getDataTable` call, and README typo (@pbchase)

## Other
- Add note/limitation regarding draft preview mode (@grezniczek)
- Add author Günther Rezniczek to CITATION.cff (@grezniczek)

# default_from_query 1.0.1 (released 2026-03-25)
- Add DOI to CITATION.cff and README.md (@pbchase)
- Set version to 1.0.1 and date-released in CITATION.cff (@pbchase)
- Remove VERSION file (@pbchase)

# default_from_query 1.0.0 (released 2026-03-23)
- Initial release (@pbchase)

## Features

### @DEFAULT-FROM-QUERY action tag
Fields annotated with `@DEFAULT-FROM-QUERY='query_name'` are automatically populated with the result of a named SQL query when a data entry form is opened for a record that has no existing data on that form.

### Project-level query configuration
Queries are defined in the module's project settings. Each query entry has a name (referenced in the action tag), a SQL statement (editable by REDCap Admins only), and up to three optional additional project IDs (`pid1`, `pid2`, `pid3`) for queries that need to reference data from other projects.

### SQL variable substitution
SQL queries may use the following placeholders, which are substituted with current context values before execution:

- `[record_id]` — the current record ID
- `[project_id]` — the current project ID
- `[field_name]` — the name of the field being populated
- `[pid1]`, `[pid2]`, `[pid3]` — the additional project IDs configured for the query
- `[data-table]` — the REDCap data table for the current project
- `[data-table:pid1]`, `[data-table:pid2]`, `[data-table:pid3]` — the REDCap data table for the corresponding additional project
