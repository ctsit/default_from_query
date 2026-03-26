<?php

/**
 * @file
 * Provides DefaultFromQuery class for Default From Query.
 */

namespace UF_CTSI\DefaultFromQuery;

use ExternalModules\AbstractExternalModule;
use Form;
use Records;

/**
 * DefaultFromQuery class for Default From Query.
 */
class DefaultFromQuery extends AbstractExternalModule
{

    const PLACEHOLDER_RECORD_ID = 'This-is-a-placeholder-for-the-real-record-id';

    /**
     * @inheritdoc
     */
    function redcap_every_page_top($project_id)
    {
        if (!$project_id) {
            return;
        }

        if (PAGE == 'DataEntry/index.php' && !empty($_GET['id'])) {
            if (!$this->currentFormHasData()) {
                $this->setDefaultValues($project_id);
            }
        }
    }

    /**
     * Sets default values for fields tagged with @DEFAULT-FROM-QUERY.
     *
     * Iterates project metadata for the current form, looks up the named query
     * from project settings, executes it, and injects the result as the value
     * of the @DEFAULT action tag so REDCap renders it as the field default.
     *
     * @param int $project_id
     */
    function setDefaultValues($project_id)
    {
        global $Proj;

        $fields = empty($_GET['page']) ? $Proj->metadata : $Proj->forms[$_GET['page']]['fields'];

        foreach (array_keys($fields) as $field_name) {
            $misc = $Proj->metadata[$field_name]['misc'];

            $query_name = Form::getValueInQuotesActionTag($misc, '@DEFAULT-FROM-QUERY');
            if (empty($query_name)) {
                continue;
            }

            $query = $this->getQueryByName($query_name);
            if ($query === null) {
                continue;
            }

            $pids = [
                'pid1' => $query['pid1'] ?? null,
                'pid2' => $query['pid2'] ?? null,
                'pid3' => $query['pid3'] ?? null,
            ];
            // Do not use $_GET['id'] because the EM scan will result in a false-positive SQL taint
            [$sql, $params] = $this->pipeSqlVariables($query['query_sql'], $project_id, $field_name, self::PLACEHOLDER_RECORD_ID, $pids);
            // Substitute record_id in $params
            $subst_idx = array_search(self::PLACEHOLDER_RECORD_ID, $params);
            if ($subst_idx !== false) {
                $params[$subst_idx] = $_GET['id'];
            }

            $value = $this->executeQuery($sql, $params);
            if ($value === null) {
                continue;
            }

            $Proj->metadata[$field_name]['misc'] = $this->overrideActionTag('@DEFAULT', $value, $misc);
        }
    }

    /**
     * Looks up a named query entry from project settings.
     *
     * @param string $query_name
     * @return array|null The query settings array, or null if not found.
     */
    function getQueryByName($query_name)
    {
        $queries = $this->getSubSettings('queries');
        foreach ($queries as $query) {
            if ($query['query_name'] === $query_name) {
                return $query;
            }
        }
        return null;
    }

    /**
     * Substitutes [record_id], [project_id], and [field_name] placeholders in
     * a SQL string with safe parameterized query markers.
     *
     * Each placeholder occurrence is replaced with a ? and the corresponding
     * value is appended to the returned params array in order, making the
     * output safe for use with a prepared statement. The [data-table]
     * placeholder is an exception: it is substituted directly as a table name
     * and cannot be bound as a parameter.
     *
     * Supported placeholders: [record_id], [project_id], [field_name],
     * [pid1], [pid2], [pid3], [data-table], [data-table:pid1],
     * [data-table:pid2], [data-table:pid3].
     *
     * @param string $sql        The raw SQL string containing placeholders.
     * @param int    $project_id The current REDCap project ID.
     * @param string $field_name The field name being processed.
     * @param string $record_id  The current record ID (from $_GET['id']).
     * @param array  $pids       Optional map of pid1/pid2/pid3 to project IDs.
     * @return array{0: string, 1: array} A tuple of [piped SQL, bound values].
     */
    function pipeSqlVariables($sql, $project_id, $field_name, $record_id, $pids = [])
    {
        // Table names cannot be bound parameters; substitute them directly.
        $sql = str_replace('[data-table]', $this->getDataTable($project_id), $sql);
        foreach (['pid1', 'pid2', 'pid3'] as $key) {
            if (!empty($pids[$key])) {
                $sql = str_replace("[data-table:$key]", $this->getDataTable($pids[$key]), $sql);
            }
        }

        $map = [
            'project_id' => $project_id,
            'field_name' => $field_name,
            'record_id'  => $record_id,
            'pid1'       => $pids['pid1'] ?? null,
            'pid2'       => $pids['pid2'] ?? null,
            'pid3'       => $pids['pid3'] ?? null,
        ];

        $params = [];
        $piped = preg_replace_callback(
            '/\[(project_id|field_name|record_id|pid1|pid2|pid3)\]/',
            function ($matches) use ($map, &$params) {
                $params[] = $map[$matches[1]];
                return '?';
            },
            $sql
        );

        return [$piped, $params];
    }

    /**
     * Executes a SQL query and returns the first column of the first row.
     *
     * @param string $sql
     * @param array  $params Bound parameter values for prepared statement placeholders.
     * @return string|null The scalar result, or null if no rows returned.
     */
    function executeQuery($sql, $params = [])
    {
        $safe = $this->checkSafeQuery($sql);
        if (!$safe) {
            return null;
        }
        try {
            $this->query('START TRANSACTION READ ONLY', []);
            $result = $this->query($sql, $params);
            $this->query('ROLLBACK', []);
            if (!$result) {
                return null;
            }
            $row = $result->fetch_row();
            return $row ? (string) $row[0] : null;
        }
        catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Injects a value into the @DEFAULT action tag within a misc field string.
     *
     * Replaces the existing @DEFAULT value if present, otherwise appends it.
     *
     * @param string $key     The action tag name (e.g. @DEFAULT).
     * @param string $value   The value to inject.
     * @param string $subject The misc field string.
     * @return string
     */
    function overrideActionTag($key, $value, $subject)
    {
        $escaped = str_replace('"', '\\"', $value);
        $pattern = '/' . preg_quote($key, '/') . '\s*=\s*("[^"]*"|\'[^\']*\')/';

        if (preg_match($pattern, $subject)) {
            return preg_replace($pattern, $key . '="' . $escaped . '"', $subject);
        }

        return $subject . ' ' . $key . '="' . $escaped . '"';
    }

    /**
     * Checks if the current form already has data.
     *
     * Prevents overwriting existing record data with defaults.
     *
     * @return bool TRUE if the form contains data, FALSE otherwise.
     */
    function currentFormHasData()
    {
        global $double_data_entry, $user_rights;

        $record = $_GET['id'];
        if ($double_data_entry && $user_rights['double_data'] != 0) {
            $record = $record . '--' . $user_rights['double_data'];
        }

        return (bool) Records::formHasData($record, $_GET['page'], $_GET['event_id'], $_GET['instance']);
    }

    /**
     * Best-effort safety check for SQL expected to be read-only.
     *
     * IMPORTANT:
     * - This is intentionally conservative.
     * - False negatives are acceptable; false positives are not.
     * - Still run accepted queries inside START TRANSACTION READ ONLY ... ROLLBACK.
     * - This is not a full SQL parser.
     */
    function checkSafeQuery(string $sql): bool
    {
        $sql = trim($sql);
        if ($sql === '') {
            return false;
        }

        // Remove comments and string literals so keyword scanning is less error-prone.
        $scan = $this->stripSqlStringsAndComments($sql);
        $scan = trim($scan);

        if ($scan === '') {
            return false;
        }

        // Reject multi-statement input.
        // We strip a single trailing semicolon and reject any remaining semicolon.
        $scanNoTrailingSemicolon = preg_replace('/;\s*$/', '', $scan);
        if (strpos($scanNoTrailingSemicolon, ';') !== false) {
            return false;
        }
        $scan = $scanNoTrailingSemicolon;

        // Normalize whitespace and lowercase for matching.
        $norm = strtolower(preg_replace('/\s+/', ' ', $scan));
        $normForStart = $this->normalizeLeadingGroupingParens($norm);

        // Allow only clearly read-style top-level starts.
        // Conservative on purpose. Add more only if you really need them.
        if (!preg_match('/^(select|with|show|describe|desc|explain)\b/', $normForStart)) {
            return false;
        }

        // EXPLAIN is only allowed for read-style statements.
        // Reject EXPLAIN UPDATE/DELETE/INSERT/etc. even though EXPLAIN itself is non-writing,
        // because the function's contract is "harmless read-like query".
        if (preg_match('/^explain\b/', $normForStart)) {
            $afterExplain = preg_replace('/^explain\s+/', '', $normForStart, 1);
            $afterExplain = $this->normalizeLeadingGroupingParens($afterExplain);
            if (!preg_match('/^(select|with|show|describe|desc)\b/', $afterExplain)) {
                return false;
            }
        }

        // Hard reject suspicious / non-harmless constructs anywhere.
        $denyPatterns = [
            // DML / write-like
            '/\binsert\b/',
            '/\bupdate\b/',
            '/\bdelete\b/',
            '/\breplace\b/',
            '/\bupsert\b/',
            '/\bmerge\b/',
            '/\bload\s+data\b/',
            '/\btruncate\b/',

            // DDL
            '/\bcreate\b/',
            '/\balter\b/',
            '/\bdrop\b/',
            '/\brename\b/',
            '/\bcomment\b/',
            '/\brepair\b/',
            '/\boptimize\b/',
            '/\banalyze\b/',   // MariaDB ANALYZE statement, not EXPLAIN ANALYZE syntax
            '/\bcheck\b/',
            '/\bcache\s+index\b/',

            // Transaction / locking / admin
            '/\block\s+tables?\b/',
            '/\bunlock\s+tables?\b/',
            '/\bfor\s+update\b/',
            '/\bfor\s+share\b/',
            '/\block\s+in\s+share\s+mode\b/',
            '/\bflush\b/',
            '/\breset\b/',
            '/\bset\s+transaction\b/',
            '/\bstart\s+transaction\b/',
            '/\bbegin\b/',
            '/\bcommit\b/',
            '/\brollback\b/',

            // File / external effects
            '/\binto\s+outfile\b/',
            '/\binto\s+dumpfile\b/',

            // Routines / dynamic SQL / eventing
            '/\bcall\b/',
            '/\bexecute\b/',
            '/\bprepare\b/',
            '/\bdeallocate\b/',
            '/\bdo\b/',
            '/\bsignal\b/',
            '/\bresignal\b/',
            '/\bhandler\b/'
        ];

        foreach ($denyPatterns as $pattern) {
            if (preg_match($pattern, $norm)) {
                return false;
            }
        }

        // Reject user-variable assignment patterns like "@x :=".
        if (preg_match('/@[\w$]+\s*:=/', $norm)) {
            return false;
        }

        // Reject SELECT ... INTO @var / local var
        // This is not a DB write, but it is a side effect.
        if (preg_match('/\bselect\b.*\binto\b\s+(@|[a-z_][a-z0-9_]*)/is', $norm)) {
            return false;
        }

        return true;
    }

    /**
     * Removes SQL comments and string/backtick literals, replacing them with spaces.
     * This avoids matching dangerous keywords that occur only inside strings/comments.
     *
     * Handles:
     * - -- comment
     * - # comment
     * - /* block comment *\/
     * - 'single quoted strings'
     * - "double quoted strings"
     * - `backtick identifiers`
     */
    function stripSqlStringsAndComments(string $sql): string
    {
        $len = strlen($sql);
        $out = '';
        $i = 0;

        while ($i < $len) {
            $ch = $sql[$i];
            $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

            // -- comment (treat --<space> and --\n conservatively as comment start)
            if ($ch === '-' && $next === '-') {
                $third = ($i + 2 < $len) ? $sql[$i + 2] : '';
                if ($third === '' || ctype_space($third)) {
                    $out .= ' ';
                    $i += 2;
                    while ($i < $len && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }
            }

            // # comment
            if ($ch === '#') {
                $out .= ' ';
                $i++;
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // /* block comment */
            if ($ch === '/' && $next === '*') {
                $out .= ' ';
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2; // skip closing */
                continue;
            }

            // Single-quoted string
            if ($ch === "'") {
                $out .= ' ';
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === "\\") {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === "'") {
                        // handle doubled single quote ''
                        if ($i + 1 < $len && $sql[$i + 1] === "'") {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // Double-quoted string
            if ($ch === '"') {
                $out .= ' ';
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === "\\") {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === '"') {
                        if ($i + 1 < $len && $sql[$i + 1] === '"') {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // Backtick identifier
            if ($ch === '`') {
                $out .= ' ';
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === '`') {
                        if ($i + 1 < $len && $sql[$i + 1] === '`') {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            $out .= $ch;
            $i++;
        }

        return $out;
    }

    /**
     * Removes leading parentheses
     * @param string $sql 
     * @return string 
     */
    function normalizeLeadingGroupingParens(string $sql): string
    {
        $sql = ltrim($sql);
        while (isset($sql[0]) && $sql[0] === '(') {
            $sql = ltrim(substr($sql, 1));
        }
        return $sql;
    }
}
