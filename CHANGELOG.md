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
