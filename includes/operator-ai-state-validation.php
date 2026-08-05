<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the expected flat file list for one AI state package.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_validation_filenames() {

    $schema = nwmd_directory_get_ai_state_export_schema();
    $files  = ['manifest.json'];

    foreach ((array) ($schema['records'] ?? []) as $record) {
        $filename = sanitize_file_name(
            (string) ($record['filename'] ?? '')
        );

        if ('' !== $filename) {
            $files[] = $filename;
        }
    }

    return array_values(array_unique($files));
}

/**
 * Load WordPress PclZip when PHP ZipArchive is unavailable.
 *
 * @return true|WP_Error
 */
function nwmd_directory_load_ai_state_pclzip() {

    if (class_exists('PclZip')) {
        return true;
    }

    $pclzip_path = ABSPATH
        . 'wp-admin/includes/class-pclzip.php';

    if (!is_readable($pclzip_path)) {
        return new WP_Error(
            'nwmd_ai_state_validation_pclzip_missing',
            __(
                'WordPress ZIP support is unavailable.',
                'local-directory-framework'
            )
        );
    }

    require_once $pclzip_path;

    if (!class_exists('PclZip')) {
        return new WP_Error(
            'nwmd_ai_state_validation_pclzip_unavailable',
            __(
                'WordPress ZIP support could not be loaded.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Validate one archive entry name.
 *
 * AI state packages must be flat. Nested paths, directory entries,
 * control characters, and unexpected names are rejected.
 *
 * @param string $filename       Archive entry name.
 * @param array  $expected_files Expected flat filenames.
 *
 * @return true|WP_Error
 */
function nwmd_directory_validate_ai_state_archive_filename(
    $filename,
    array $expected_files
) {

    $filename = (string) $filename;

    if (
        '' === $filename
        || basename($filename) !== $filename
        || false !== strpos($filename, '/')
        || false !== strpos($filename, '\\')
        || false !== strpos($filename, '..')
        || 1 === preg_match('/[\x00-\x1F\x7F]/', $filename)
    ) {
        return new WP_Error(
            'nwmd_ai_state_validation_archive_path_invalid',
            __(
                'The AI state ZIP contains an unsafe or nested path.',
                'local-directory-framework'
            )
        );
    }

    if (!in_array($filename, $expected_files, true)) {
        return new WP_Error(
            'nwmd_ai_state_validation_archive_file_unexpected',
            sprintf(
                __(
                    'The AI state ZIP contains an unexpected file: %s.',
                    'local-directory-framework'
                ),
                sanitize_file_name($filename)
            )
        );
    }

    return true;
}

/**
 * Read every expected file using PHP ZipArchive.
 *
 * Contents are read into memory instead of extracted to the server.
 *
 * @param string $zip_path       Uploaded ZIP path.
 * @param array  $expected_files Expected flat filenames.
 *
 * @return array|WP_Error
 */
function nwmd_directory_read_ai_state_ziparchive_files(
    $zip_path,
    array $expected_files
) {

    $archive = new ZipArchive();
    $opened  = $archive->open($zip_path);

    if (true !== $opened) {
        return new WP_Error(
            'nwmd_ai_state_validation_zip_open_failed',
            __(
                'The uploaded AI state ZIP could not be opened.',
                'local-directory-framework'
            )
        );
    }

    if ($archive->numFiles !== count($expected_files)) {
        $archive->close();

        return new WP_Error(
            'nwmd_ai_state_validation_zip_file_count',
            sprintf(
                __(
                    'The AI state ZIP must contain exactly %d files.',
                    'local-directory-framework'
                ),
                count($expected_files)
            )
        );
    }

    $files          = [];
    $expanded_bytes = 0;

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $stat = $archive->statIndex($index);

        if (!is_array($stat)) {
            $archive->close();

            return new WP_Error(
                'nwmd_ai_state_validation_zip_entry_invalid',
                __(
                    'The AI state ZIP contains an unreadable entry.',
                    'local-directory-framework'
                )
            );
        }

        $filename = (string) ($stat['name'] ?? '');

        $valid =
            nwmd_directory_validate_ai_state_archive_filename(
                $filename,
                $expected_files
            );

        if (is_wp_error($valid)) {
            $archive->close();

            return $valid;
        }

        if (isset($files[$filename])) {
            $archive->close();

            return new WP_Error(
                'nwmd_ai_state_validation_zip_duplicate_file',
                sprintf(
                    __(
                        'The AI state ZIP contains a duplicate file: %s.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $declared_size = isset($stat['size'])
            ? absint($stat['size'])
            : 0;

        if ($declared_size > 8 * MB_IN_BYTES) {
            $archive->close();

            return new WP_Error(
                'nwmd_ai_state_validation_zip_entry_too_large',
                sprintf(
                    __(
                        'The AI state file "%s" exceeds the 8 MB limit.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $contents = $archive->getFromIndex($index);

        if (!is_string($contents)) {
            $archive->close();

            return new WP_Error(
                'nwmd_ai_state_validation_zip_entry_read_failed',
                sprintf(
                    __(
                        'The AI state file "%s" could not be read.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        if (strlen($contents) !== $declared_size) {
            $archive->close();

            return new WP_Error(
                'nwmd_ai_state_validation_zip_entry_size_mismatch',
                sprintf(
                    __(
                        'The AI state file "%s" has an invalid expanded size.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $expanded_bytes += strlen($contents);

        if ($expanded_bytes > 25 * MB_IN_BYTES) {
            $archive->close();

            return new WP_Error(
                'nwmd_ai_state_validation_zip_expanded_limit',
                __(
                    'The expanded AI state package exceeds the 25 MB limit.',
                    'local-directory-framework'
                )
            );
        }

        $files[$filename] = $contents;
    }

    $archive->close();

    foreach ($expected_files as $expected_file) {
        if (!array_key_exists($expected_file, $files)) {
            return new WP_Error(
                'nwmd_ai_state_validation_zip_required_file_missing',
                sprintf(
                    __(
                        'The AI state ZIP is missing the required file: %s.',
                        'local-directory-framework'
                    ),
                    $expected_file
                )
            );
        }
    }

    return $files;
}

/**
 * Read every expected file using WordPress PclZip.
 *
 * @param string $zip_path       Uploaded ZIP path.
 * @param array  $expected_files Expected flat filenames.
 *
 * @return array|WP_Error
 */
function nwmd_directory_read_ai_state_pclzip_files(
    $zip_path,
    array $expected_files
) {

    $loaded = nwmd_directory_load_ai_state_pclzip();

    if (is_wp_error($loaded)) {
        return $loaded;
    }

    $archive = new PclZip($zip_path);
    $entries = $archive->listContent();

    if (!is_array($entries)) {
        return new WP_Error(
            'nwmd_ai_state_validation_pclzip_list_failed',
            __(
                'The uploaded AI state ZIP could not be inspected.',
                'local-directory-framework'
            )
        );
    }

    if (count($entries) !== count($expected_files)) {
        return new WP_Error(
            'nwmd_ai_state_validation_zip_file_count',
            sprintf(
                __(
                    'The AI state ZIP must contain exactly %d files.',
                    'local-directory-framework'
                ),
                count($expected_files)
            )
        );
    }

    $files          = [];
    $expanded_bytes = 0;

    foreach ($entries as $entry) {
        if (!is_array($entry) || !empty($entry['folder'])) {
            return new WP_Error(
                'nwmd_ai_state_validation_archive_directory',
                __(
                    'The AI state ZIP must not contain directories.',
                    'local-directory-framework'
                )
            );
        }

        $filename = (string) ($entry['filename'] ?? '');

        $valid =
            nwmd_directory_validate_ai_state_archive_filename(
                $filename,
                $expected_files
            );

        if (is_wp_error($valid)) {
            return $valid;
        }

        if (isset($files[$filename])) {
            return new WP_Error(
                'nwmd_ai_state_validation_zip_duplicate_file',
                sprintf(
                    __(
                        'The AI state ZIP contains a duplicate file: %s.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $declared_size = isset($entry['size'])
            ? absint($entry['size'])
            : 0;

        if ($declared_size > 8 * MB_IN_BYTES) {
            return new WP_Error(
                'nwmd_ai_state_validation_zip_entry_too_large',
                sprintf(
                    __(
                        'The AI state file "%s" exceeds the 8 MB limit.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $extracted = $archive->extract(
            PCLZIP_OPT_BY_NAME,
            $filename,
            PCLZIP_OPT_EXTRACT_AS_STRING
        );

        if (
            !is_array($extracted)
            || 1 !== count($extracted)
            || !isset($extracted[0]['content'])
            || !is_string($extracted[0]['content'])
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_pclzip_read_failed',
                sprintf(
                    __(
                        'The AI state file "%s" could not be read.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $contents = $extracted[0]['content'];

        if (strlen($contents) !== $declared_size) {
            return new WP_Error(
                'nwmd_ai_state_validation_zip_entry_size_mismatch',
                sprintf(
                    __(
                        'The AI state file "%s" has an invalid expanded size.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $expanded_bytes += strlen($contents);

        if ($expanded_bytes > 25 * MB_IN_BYTES) {
            return new WP_Error(
                'nwmd_ai_state_validation_zip_expanded_limit',
                __(
                    'The expanded AI state package exceeds the 25 MB limit.',
                    'local-directory-framework'
                )
            );
        }

        $files[$filename] = $contents;
    }

    foreach ($expected_files as $expected_file) {
        if (!array_key_exists($expected_file, $files)) {
            return new WP_Error(
                'nwmd_ai_state_validation_zip_required_file_missing',
                sprintf(
                    __(
                        'The AI state ZIP is missing the required file: %s.',
                        'local-directory-framework'
                    ),
                    $expected_file
                )
            );
        }
    }

    return $files;
}

/**
 * Read the flat files from one uploaded AI state ZIP.
 *
 * @param string $zip_path Uploaded ZIP path.
 *
 * @return array|WP_Error
 */
function nwmd_directory_read_ai_state_zip_files($zip_path) {

    $expected_files =
        nwmd_directory_get_ai_state_validation_filenames();

    if (class_exists('ZipArchive')) {
        return nwmd_directory_read_ai_state_ziparchive_files(
            $zip_path,
            $expected_files
        );
    }

    return nwmd_directory_read_ai_state_pclzip_files(
        $zip_path,
        $expected_files
    );
}

/**
 * Validate and decode an AI state manifest.
 *
 * @param string $contents Raw manifest JSON.
 *
 * @return array|WP_Error
 */
function nwmd_directory_validate_ai_state_manifest($contents) {

    $contents = (string) $contents;

    if ('' === $contents) {
        return new WP_Error(
            'nwmd_ai_state_validation_manifest_empty',
            __(
                'The AI state manifest is empty.',
                'local-directory-framework'
            )
        );
    }

    if (strlen($contents) > 256 * KB_IN_BYTES) {
        return new WP_Error(
            'nwmd_ai_state_validation_manifest_too_large',
            __(
                'The AI state manifest exceeds the 256 KB limit.',
                'local-directory-framework'
            )
        );
    }

    if (0 === strncmp($contents, "\xEF\xBB\xBF", 3)) {
        $contents = substr($contents, 3);
    }

    $manifest = json_decode($contents, true);

    if (
        JSON_ERROR_NONE !== json_last_error()
        || !is_array($manifest)
    ) {
        return new WP_Error(
            'nwmd_ai_state_validation_manifest_json_invalid',
            __(
                'The AI state manifest contains invalid JSON.',
                'local-directory-framework'
            )
        );
    }

    $expected_manifest_keys = [
        'files',
        'format_version',
        'generated_at_utc',
        'plugin_version',
        'source_site',
        'taxonomy_separator',
    ];

    $actual_manifest_keys = array_keys($manifest);

    sort($expected_manifest_keys);
    sort($actual_manifest_keys);

    if ($actual_manifest_keys !== $expected_manifest_keys) {
        return new WP_Error(
            'nwmd_ai_state_validation_manifest_schema',
            __(
                'The AI state manifest does not match the required schema.',
                'local-directory-framework'
            )
        );
    }

    $schema = nwmd_directory_get_ai_state_export_schema();

    if (
        (string) ($manifest['format_version'] ?? '')
        !== (string) ($schema['format_version'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_ai_state_validation_format_version',
            sprintf(
                __(
                    'The AI state package must use format version %s.',
                    'local-directory-framework'
                ),
                (string) ($schema['format_version'] ?? '')
            )
        );
    }

    if (
        (string) ($manifest['taxonomy_separator'] ?? '')
        !== (string) ($schema['taxonomy_separator'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_ai_state_validation_taxonomy_separator',
            __(
                'The AI state package uses an invalid taxonomy separator.',
                'local-directory-framework'
            )
        );
    }

    $plugin_version = (string) (
        $manifest['plugin_version'] ?? ''
    );

    if (
        '' === $plugin_version
        || strlen($plugin_version) > 32
    ) {
        return new WP_Error(
            'nwmd_ai_state_validation_plugin_version',
            __(
                'The AI state manifest has an invalid plugin version.',
                'local-directory-framework'
            )
        );
    }

    $generated_at = (string) (
        $manifest['generated_at_utc'] ?? ''
    );

    if (
        1 !== preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $generated_at
        )
        || false === strtotime($generated_at)
    ) {
        return new WP_Error(
            'nwmd_ai_state_validation_generated_at',
            __(
                'The AI state manifest has an invalid UTC generation time.',
                'local-directory-framework'
            )
        );
    }

    $source_site = (string) (
        $manifest['source_site'] ?? ''
    );

    if (
        '' === $source_site
        || strlen($source_site) > 2048
        || false === wp_http_validate_url($source_site)
    ) {
        return new WP_Error(
            'nwmd_ai_state_validation_source_site',
            __(
                'The AI state manifest has an invalid source site.',
                'local-directory-framework'
            )
        );
    }

    $manifest_files = $manifest['files'] ?? null;

    if (!is_array($manifest_files)) {
        return new WP_Error(
            'nwmd_ai_state_validation_manifest_files',
            __(
                'The AI state manifest does not contain valid file metadata.',
                'local-directory-framework'
            )
        );
    }

    $expected_file_keys = [];

    foreach ((array) ($schema['records'] ?? []) as $record) {
        $expected_file_keys[] = (string) (
            $record['filename'] ?? ''
        );
    }

    $actual_file_keys = array_keys($manifest_files);

    sort($expected_file_keys);
    sort($actual_file_keys);

    if ($actual_file_keys !== $expected_file_keys) {
        return new WP_Error(
            'nwmd_ai_state_validation_manifest_file_list',
            __(
                'The AI state manifest file list is incomplete or contains unexpected files.',
                'local-directory-framework'
            )
        );
    }

    $expected_metadata_keys = [
        'bytes',
        'columns',
        'record_name',
        'rows',
        'sha256',
    ];

    sort($expected_metadata_keys);

    foreach ((array) ($schema['records'] ?? []) as $record_name => $record) {
        $filename = (string) (
            $record['filename'] ?? ''
        );
        $metadata = $manifest_files[$filename] ?? null;

        if (!is_array($metadata)) {
            return new WP_Error(
                'nwmd_ai_state_validation_manifest_file_metadata',
                sprintf(
                    __(
                        'The manifest metadata for "%s" is invalid.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $actual_metadata_keys = array_keys($metadata);

        sort($actual_metadata_keys);

        if ($actual_metadata_keys !== $expected_metadata_keys) {
            return new WP_Error(
                'nwmd_ai_state_validation_manifest_file_schema',
                sprintf(
                    __(
                        'The manifest metadata for "%s" does not match the required schema.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        if (
            (string) ($metadata['record_name'] ?? '')
            !== (string) $record_name
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_manifest_record_name',
                sprintf(
                    __(
                        'The manifest record name for "%s" is invalid.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $checksum = (string) (
            $metadata['sha256'] ?? ''
        );

        if (
            1 !== preg_match(
                '/^[a-f0-9]{64}$/',
                $checksum
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_manifest_checksum',
                sprintf(
                    __(
                        'The manifest checksum for "%s" is invalid.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        if (
            !is_int($metadata['bytes'])
            || $metadata['bytes'] < 0
            || !is_int($metadata['rows'])
            || $metadata['rows'] < 0
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_manifest_counts',
                sprintf(
                    __(
                        'The manifest counts for "%s" are invalid.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        if (
            !is_array($metadata['columns'])
            || array_values($metadata['columns'])
                !== array_values(
                    (array) ($record['columns'] ?? [])
                )
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_manifest_columns',
                sprintf(
                    __(
                        'The manifest columns for "%s" do not match the required schema.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }
    }

    return $manifest;
}

/**
 * Parse and validate one AI state CSV document.
 *
 * @param string $contents         Raw CSV contents.
 * @param array  $expected_columns Required columns in exact order.
 * @param string $filename         Package filename.
 *
 * @return array|WP_Error
 */
function nwmd_directory_parse_ai_state_csv(
    $contents,
    array $expected_columns,
    $filename
) {

    $contents = (string) $contents;
    $filename = sanitize_file_name((string) $filename);

    if ('' === $contents) {
        return new WP_Error(
            'nwmd_ai_state_validation_csv_empty',
            sprintf(
                __(
                    'The AI state file "%s" is empty.',
                    'local-directory-framework'
                ),
                $filename
            )
        );
    }

    if (strlen($contents) > 8 * MB_IN_BYTES) {
        return new WP_Error(
            'nwmd_ai_state_validation_csv_too_large',
            sprintf(
                __(
                    'The AI state file "%s" exceeds the 8 MB limit.',
                    'local-directory-framework'
                ),
                $filename
            )
        );
    }

    $handle = fopen(
        'php://temp/maxmemory:1048576',
        'w+b'
    );

    if (false === $handle) {
        return new WP_Error(
            'nwmd_ai_state_validation_csv_stream_failed',
            sprintf(
                __(
                    'The AI state file "%s" could not be prepared for validation.',
                    'local-directory-framework'
                ),
                $filename
            )
        );
    }

    $written = fwrite($handle, $contents);

    if (
        false === $written
        || $written !== strlen($contents)
    ) {
        fclose($handle);

        return new WP_Error(
            'nwmd_ai_state_validation_csv_stream_write_failed',
            sprintf(
                __(
                    'The AI state file "%s" could not be read completely.',
                    'local-directory-framework'
                ),
                $filename
            )
        );
    }

    rewind($handle);

    $first_bytes = fread($handle, 3);

    if ("\xEF\xBB\xBF" !== $first_bytes) {
        rewind($handle);
    }

    $headers = fgetcsv(
        $handle,
        0,
        ',',
        '"',
        '\\'
    );

    if (!is_array($headers) || empty($headers)) {
        fclose($handle);

        return new WP_Error(
            'nwmd_ai_state_validation_csv_header_missing',
            sprintf(
                __(
                    'The AI state file "%s" has a missing or unreadable header.',
                    'local-directory-framework'
                ),
                $filename
            )
        );
    }

    $headers = array_map(
        static function ($header) {
            return (string) $header;
        },
        $headers
    );

    $headers[0] = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        $headers[0]
    );

    if (
        array_values($headers)
        !== array_values($expected_columns)
    ) {
        fclose($handle);

        return new WP_Error(
            'nwmd_ai_state_validation_csv_header_schema',
            sprintf(
                __(
                    'The AI state file "%s" columns do not match the required schema.',
                    'local-directory-framework'
                ),
                $filename
            )
        );
    }

    $rows        = [];
    $line_number = 1;

    while (
        false !== (
            $values = fgetcsv(
                $handle,
                0,
                ',',
                '"',
                '\\'
            )
        )
    ) {
        $line_number++;

        $non_empty_values = array_filter(
            $values,
            static function ($value) {
                return '' !== trim((string) $value);
            }
        );

        if (empty($non_empty_values)) {
            continue;
        }

        if (count($rows) >= 100000) {
            fclose($handle);

            return new WP_Error(
                'nwmd_ai_state_validation_csv_row_limit',
                sprintf(
                    __(
                        'The AI state file "%s" exceeds the 100,000-row limit.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        if (count($values) !== count($headers)) {
            fclose($handle);

            return new WP_Error(
                'nwmd_ai_state_validation_csv_row_width',
                sprintf(
                    __(
                        'The AI state file "%1$s" has an invalid column count on line %2$d.',
                        'local-directory-framework'
                    ),
                    $filename,
                    $line_number
                )
            );
        }

        $row = array_combine($headers, $values);

        if (!is_array($row)) {
            fclose($handle);

            return new WP_Error(
                'nwmd_ai_state_validation_csv_row_mapping',
                sprintf(
                    __(
                        'The AI state file "%1$s" could not map line %2$d to its columns.',
                        'local-directory-framework'
                    ),
                    $filename,
                    $line_number
                )
            );
        }

        $rows[] = $row;
    }

    if (!feof($handle)) {
        fclose($handle);

        return new WP_Error(
            'nwmd_ai_state_validation_csv_incomplete',
            sprintf(
                __(
                    'The AI state file "%s" could not be read completely.',
                    'local-directory-framework'
                ),
                $filename
            )
        );
    }

    fclose($handle);

    return $rows;
}

/**
 * Validate package metadata, checksums, file sizes, headers, and row counts.
 *
 * This function is read-only and performs no database writes.
 *
 * @param array $files Raw package files keyed by filename.
 *
 * @return array|WP_Error
 */
function nwmd_directory_validate_ai_state_package_structure(
    array $files
) {

    if (!isset($files['manifest.json'])) {
        return new WP_Error(
            'nwmd_ai_state_validation_manifest_missing',
            __(
                'The AI state ZIP does not contain manifest.json.',
                'local-directory-framework'
            )
        );
    }

    $manifest =
        nwmd_directory_validate_ai_state_manifest(
            $files['manifest.json']
        );

    if (is_wp_error($manifest)) {
        return $manifest;
    }

    $schema         = nwmd_directory_get_ai_state_export_schema();
    $manifest_files = $manifest['files'];
    $records        = [];
    $counts         = [];
    $total_rows     = 0;

    foreach ((array) ($schema['records'] ?? []) as $record_name => $record) {
        $filename = (string) (
            $record['filename'] ?? ''
        );
        $contents = $files[$filename] ?? null;

        if (!is_string($contents)) {
            return new WP_Error(
                'nwmd_ai_state_validation_package_file_missing',
                sprintf(
                    __(
                        'The AI state package is missing "%s".',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $metadata = $manifest_files[$filename];

        if (strlen($contents) !== $metadata['bytes']) {
            return new WP_Error(
                'nwmd_ai_state_validation_package_bytes',
                sprintf(
                    __(
                        'The byte count for "%s" does not match the manifest.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $actual_checksum = hash(
            'sha256',
            $contents
        );

        if (
            !is_string($actual_checksum)
            || !hash_equals(
                $metadata['sha256'],
                $actual_checksum
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_package_checksum',
                sprintf(
                    __(
                        'The checksum for "%s" does not match the manifest.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $rows = nwmd_directory_parse_ai_state_csv(
            $contents,
            (array) ($record['columns'] ?? []),
            $filename
        );

        if (is_wp_error($rows)) {
            return $rows;
        }

        $row_count = count($rows);

        if ($row_count !== $metadata['rows']) {
            return new WP_Error(
                'nwmd_ai_state_validation_package_rows',
                sprintf(
                    __(
                        'The row count for "%s" does not match the manifest.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $total_rows += $row_count;

        if ($total_rows > 250000) {
            return new WP_Error(
                'nwmd_ai_state_validation_package_row_limit',
                __(
                    'The AI state package exceeds the 250,000-row limit.',
                    'local-directory-framework'
                )
            );
        }

        $records[$record_name] = $rows;
        $counts[$record_name]  = $row_count;
    }

    return [
        'manifest' => $manifest,
        'records'  => $records,
        'counts'   => $counts,
    ];
}

/**
 * Split one portable taxonomy slug list.
 *
 * @param string $value     Separator-delimited slug list.
 * @param string $separator Taxonomy separator.
 *
 * @return array
 */
function nwmd_directory_parse_ai_state_slug_list(
    $value,
    $separator
) {

    $value     = trim((string) $value);
    $separator = (string) $separator;

    if ('' === $value) {
        return [];
    }

    $slugs = array_map(
        'trim',
        explode($separator, $value)
    );

    return array_values(
        array_filter(
            array_unique($slugs),
            static function ($slug) {
                return '' !== $slug;
            }
        )
    );
}

/**
 * Return one normalized taxonomy identity.
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $slug     Term slug.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_taxonomy_key(
    $taxonomy,
    $slug
) {

    return (string) $taxonomy
        . '|'
        . (string) $slug;
}

/**
 * Validate duplicate identities and cross-record relationships.
 *
 * This function is read-only and performs no WordPress data writes.
 *
 * @param array $package Structurally validated AI state package.
 *
 * @return array|WP_Error
 */
function nwmd_directory_validate_ai_state_record_relationships(
    array $package
) {

    $records = isset($package['records'])
        && is_array($package['records'])
            ? $package['records']
            : [];

    $manifest = isset($package['manifest'])
        && is_array($package['manifest'])
            ? $package['manifest']
            : [];

    $separator = (string) (
        $manifest['taxonomy_separator'] ?? '|'
    );

    $businesses = isset($records['businesses'])
        && is_array($records['businesses'])
            ? $records['businesses']
            : [];

    $sources = isset($records['sources'])
        && is_array($records['sources'])
            ? $records['sources']
            : [];

    $deals = isset($records['deals'])
        && is_array($records['deals'])
            ? $records['deals']
            : [];

    $queue = isset($records['queue'])
        && is_array($records['queue'])
            ? $records['queue']
            : [];

    $runs = isset($records['runs'])
        && is_array($records['runs'])
            ? $records['runs']
            : [];

    $taxonomy_rows = isset($records['taxonomy_terms'])
        && is_array($records['taxonomy_terms'])
            ? $records['taxonomy_terms']
            : [];

    $allowed_taxonomies = [
        'nwmd_state',
        'nwmd_city',
        'nwmd_category',
        'nwmd_specialty',
    ];

    $taxonomy_lookup = [];
    $taxonomy_counts = array_fill_keys(
        $allowed_taxonomies,
        0
    );

    foreach ($taxonomy_rows as $index => $row) {
        $row_number = $index + 2;
        $taxonomy   = trim(
            (string) ($row['taxonomy'] ?? '')
        );
        $term_slug  = trim(
            (string) ($row['term_slug'] ?? '')
        );

        if (
            !in_array($taxonomy, $allowed_taxonomies, true)
            || '' === $term_slug
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_taxonomy_identity',
                sprintf(
                    __(
                        'taxonomy-terms.csv has an invalid taxonomy identity on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $key = nwmd_directory_get_ai_state_taxonomy_key(
            $taxonomy,
            $term_slug
        );

        if (isset($taxonomy_lookup[$key])) {
            return new WP_Error(
                'nwmd_ai_state_validation_taxonomy_duplicate',
                sprintf(
                    __(
                        'taxonomy-terms.csv contains duplicate term "%1$s" in "%2$s".',
                        'local-directory-framework'
                    ),
                    $term_slug,
                    $taxonomy
                )
            );
        }

        $taxonomy_lookup[$key] = $row;
        $taxonomy_counts[$taxonomy]++;
    }

    foreach ($taxonomy_rows as $index => $row) {
        $row_number = $index + 2;

        $taxonomy = trim(
            (string) ($row['taxonomy'] ?? '')
        );

        $parent_taxonomy = trim(
            (string) ($row['parent_taxonomy'] ?? '')
        );

        $parent_slug = trim(
            (string) ($row['parent_slug'] ?? '')
        );

        if (
            ('' === $parent_taxonomy)
            !== ('' === $parent_slug)
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_taxonomy_parent_pair',
                sprintf(
                    __(
                        'taxonomy-terms.csv has incomplete parent metadata on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        if ('' !== $parent_taxonomy) {
            $parent_key =
                nwmd_directory_get_ai_state_taxonomy_key(
                    $parent_taxonomy,
                    $parent_slug
                );

            if (!isset($taxonomy_lookup[$parent_key])) {
                return new WP_Error(
                    'nwmd_ai_state_validation_taxonomy_parent_missing',
                    sprintf(
                        __(
                            'taxonomy-terms.csv references a missing parent term on line %d.',
                            'local-directory-framework'
                        ),
                        $row_number
                    )
                );
            }
        }

        $related_taxonomy = trim(
            (string) ($row['related_taxonomy'] ?? '')
        );

        $related_slug = trim(
            (string) ($row['related_slug'] ?? '')
        );

        if (
            ('' === $related_taxonomy)
            !== ('' === $related_slug)
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_taxonomy_relation_pair',
                sprintf(
                    __(
                        'taxonomy-terms.csv has incomplete relationship metadata on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        if ('' === $related_taxonomy) {
            continue;
        }

        $required_related_taxonomy = '';

        if ('nwmd_city' === $taxonomy) {
            $required_related_taxonomy = 'nwmd_state';
        } elseif ('nwmd_specialty' === $taxonomy) {
            $required_related_taxonomy = 'nwmd_category';
        } else {
            return new WP_Error(
                'nwmd_ai_state_validation_taxonomy_relation_unexpected',
                sprintf(
                    __(
                        'taxonomy-terms.csv contains an unexpected relationship on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        if ($related_taxonomy !== $required_related_taxonomy) {
            return new WP_Error(
                'nwmd_ai_state_validation_taxonomy_relation_type',
                sprintf(
                    __(
                        'taxonomy-terms.csv has an invalid relationship type on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $related_key =
            nwmd_directory_get_ai_state_taxonomy_key(
                $related_taxonomy,
                $related_slug
            );

        if (!isset($taxonomy_lookup[$related_key])) {
            return new WP_Error(
                'nwmd_ai_state_validation_taxonomy_relation_missing',
                sprintf(
                    __(
                        'taxonomy-terms.csv references a missing related term on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }
    }

    $business_lookup = [];

    foreach ($businesses as $index => $row) {
        $row_number   = $index + 2;
        $business_slug = trim(
            (string) ($row['business_slug'] ?? '')
        );

        if ('' === $business_slug) {
            return new WP_Error(
                'nwmd_ai_state_validation_business_slug_missing',
                sprintf(
                    __(
                        'businesses.csv has a missing business slug on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        if (isset($business_lookup[$business_slug])) {
            return new WP_Error(
                'nwmd_ai_state_validation_business_duplicate',
                sprintf(
                    __(
                        'businesses.csv contains duplicate business slug "%s".',
                        'local-directory-framework'
                    ),
                    $business_slug
                )
            );
        }

        $business_lookup[$business_slug] = true;

        $taxonomy_fields = [
            'state_slugs' =>
                'nwmd_state',
            'city_slugs' =>
                'nwmd_city',
            'category_slugs' =>
                'nwmd_category',
            'specialty_slugs' =>
                'nwmd_specialty',
        ];

        foreach ($taxonomy_fields as $field => $taxonomy) {
            $slugs = nwmd_directory_parse_ai_state_slug_list(
                $row[$field] ?? '',
                $separator
            );

            foreach ($slugs as $slug) {
                $term_key =
                    nwmd_directory_get_ai_state_taxonomy_key(
                        $taxonomy,
                        $slug
                    );

                if (!isset($taxonomy_lookup[$term_key])) {
                    return new WP_Error(
                        'nwmd_ai_state_validation_business_taxonomy_missing',
                        sprintf(
                            __(
                                'businesses.csv references missing taxonomy term "%1$s" on line %2$d.',
                                'local-directory-framework'
                            ),
                            $slug,
                            $row_number
                        )
                    );
                }
            }
        }
    }

    $source_lookup = [];

    foreach ($sources as $index => $row) {
        $row_number    = $index + 2;
        $business_slug = trim(
            (string) ($row['business_slug'] ?? '')
        );

        if (!isset($business_lookup[$business_slug])) {
            return new WP_Error(
                'nwmd_ai_state_validation_source_orphan',
                sprintf(
                    __(
                        'business-sources.csv references a missing business on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $source_key = implode(
            '|',
            [
                $business_slug,
                trim((string) ($row['source_type'] ?? '')),
                trim((string) ($row['source_identifier'] ?? '')),
                trim((string) ($row['source_url'] ?? '')),
            ]
        );

        if (isset($source_lookup[$source_key])) {
            return new WP_Error(
                'nwmd_ai_state_validation_source_duplicate',
                sprintf(
                    __(
                        'business-sources.csv contains a duplicate source identity on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $source_lookup[$source_key] = true;
    }

    $deal_lookup = [];

    foreach ($deals as $index => $row) {
        $row_number    = $index + 2;
        $business_slug = trim(
            (string) ($row['business_slug'] ?? '')
        );
        $deal_slug = trim(
            (string) ($row['deal_slug'] ?? '')
        );

        if (!isset($business_lookup[$business_slug])) {
            return new WP_Error(
                'nwmd_ai_state_validation_deal_orphan',
                sprintf(
                    __(
                        'business-deals.csv references a missing business on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        if ('' === $deal_slug) {
            return new WP_Error(
                'nwmd_ai_state_validation_deal_slug_missing',
                sprintf(
                    __(
                        'business-deals.csv has a missing deal slug on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $deal_key = $business_slug
            . '|'
            . $deal_slug;

        if (isset($deal_lookup[$deal_key])) {
            return new WP_Error(
                'nwmd_ai_state_validation_deal_duplicate',
                sprintf(
                    __(
                        'business-deals.csv contains duplicate Deal "%1$s" for business "%2$s".',
                        'local-directory-framework'
                    ),
                    $deal_slug,
                    $business_slug
                )
            );
        }

        $deal_lookup[$deal_key] = true;
    }

    $job_lookup        = [];
    $job_identity_keys = [];
    $checkpoint_lookup = [];

    foreach ($queue as $index => $row) {
        $row_number = $index + 2;

        $job_key = trim(
            (string) ($row['job_key'] ?? '')
        );

        $state_slug = trim(
            (string) ($row['state_slug'] ?? '')
        );

        $city_slug = trim(
            (string) ($row['city_slug'] ?? '')
        );

        $category_slug = trim(
            (string) ($row['category_slug'] ?? '')
        );

        $specialty_slug = trim(
            (string) ($row['specialty_slug'] ?? '')
        );

        if (
            '' === $job_key
            || '' === $state_slug
            || '' === $city_slug
            || '' === $category_slug
            || '' === $specialty_slug
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_queue_identity_missing',
                sprintf(
                    __(
                        'research-queue.csv has an incomplete checkpoint identity on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $term_requirements = [
            'nwmd_state' =>
                $state_slug,
            'nwmd_city' =>
                $city_slug,
            'nwmd_category' =>
                $category_slug,
            'nwmd_specialty' =>
                $specialty_slug,
        ];

        foreach ($term_requirements as $taxonomy => $slug) {
            $term_key =
                nwmd_directory_get_ai_state_taxonomy_key(
                    $taxonomy,
                    $slug
                );

            if (!isset($taxonomy_lookup[$term_key])) {
                return new WP_Error(
                    'nwmd_ai_state_validation_queue_taxonomy_missing',
                    sprintf(
                        __(
                            'research-queue.csv references missing term "%1$s" on line %2$d.',
                            'local-directory-framework'
                        ),
                        $slug,
                        $row_number
                    )
                );
            }
        }

        $city_key =
            nwmd_directory_get_ai_state_taxonomy_key(
                'nwmd_city',
                $city_slug
            );

        $city_row = $taxonomy_lookup[$city_key];

        if (
            'nwmd_state'
                !== trim(
                    (string) (
                        $city_row['related_taxonomy']
                        ?? ''
                    )
                )
            || $state_slug
                !== trim(
                    (string) (
                        $city_row['related_slug']
                        ?? ''
                    )
                )
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_queue_city_state',
                sprintf(
                    __(
                        'research-queue.csv has a city/state relationship mismatch on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $specialty_key =
            nwmd_directory_get_ai_state_taxonomy_key(
                'nwmd_specialty',
                $specialty_slug
            );

        $specialty_row =
            $taxonomy_lookup[$specialty_key];

        if (
            'nwmd_category'
                !== trim(
                    (string) (
                        $specialty_row['related_taxonomy']
                        ?? ''
                    )
                )
            || $category_slug
                !== trim(
                    (string) (
                        $specialty_row['related_slug']
                        ?? ''
                    )
                )
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_queue_specialty_category',
                sprintf(
                    __(
                        'research-queue.csv has a specialty/category relationship mismatch on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $job_identity = implode(
            '|',
            [
                $state_slug,
                $city_slug,
                $category_slug,
            ]
        );

        if (
            isset($job_lookup[$job_key])
            && $job_lookup[$job_key] !== $job_identity
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_queue_job_key_conflict',
                sprintf(
                    __(
                        'research-queue.csv assigns job key "%s" to multiple job identities.',
                        'local-directory-framework'
                    ),
                    $job_key
                )
            );
        }

        if (
            isset($job_identity_keys[$job_identity])
            && $job_identity_keys[$job_identity] !== $job_key
        ) {
            return new WP_Error(
                'nwmd_ai_state_validation_queue_job_identity_conflict',
                sprintf(
                    __(
                        'research-queue.csv assigns multiple job keys to the same city-category job on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $job_lookup[$job_key] =
            $job_identity;

        $job_identity_keys[$job_identity] =
            $job_key;

        $checkpoint_key = $job_key
            . '|'
            . $specialty_slug;

        if (isset($checkpoint_lookup[$checkpoint_key])) {
            return new WP_Error(
                'nwmd_ai_state_validation_queue_checkpoint_duplicate',
                sprintf(
                    __(
                        'research-queue.csv contains duplicate checkpoint "%1$s" for job "%2$s".',
                        'local-directory-framework'
                    ),
                    $specialty_slug,
                    $job_key
                )
            );
        }

        $checkpoint_lookup[$checkpoint_key] = [
            'state_slug' =>
                $state_slug,
            'city_slug' =>
                $city_slug,
            'category_slug' =>
                $category_slug,
            'specialty_slug' =>
                $specialty_slug,
        ];
    }

    $run_lookup = [];

    foreach ($runs as $index => $row) {
        $row_number = $index + 2;
        $run_uuid = trim(
            (string) ($row['run_uuid'] ?? '')
        );

        if ('' === $run_uuid) {
            return new WP_Error(
                'nwmd_ai_state_validation_run_uuid_missing',
                sprintf(
                    __(
                        'operator-runs.csv has a missing run UUID on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        if (isset($run_lookup[$run_uuid])) {
            return new WP_Error(
                'nwmd_ai_state_validation_run_duplicate',
                sprintf(
                    __(
                        'operator-runs.csv contains duplicate run UUID "%s".',
                        'local-directory-framework'
                    ),
                    $run_uuid
                )
            );
        }

        $run_lookup[$run_uuid] = true;

        $job_key = trim(
            (string) ($row['job_key'] ?? '')
        );

        $specialty_slug = trim(
            (string) ($row['specialty_slug'] ?? '')
        );

        $checkpoint_key = $job_key
            . '|'
            . $specialty_slug;

        if (!isset($checkpoint_lookup[$checkpoint_key])) {
            return new WP_Error(
                'nwmd_ai_state_validation_run_checkpoint_missing',
                sprintf(
                    __(
                        'operator-runs.csv references a missing queue checkpoint on line %d.',
                        'local-directory-framework'
                    ),
                    $row_number
                )
            );
        }

        $checkpoint =
            $checkpoint_lookup[$checkpoint_key];

        foreach (
            [
                'state_slug',
                'city_slug',
                'category_slug',
                'specialty_slug',
            ] as $field
        ) {
            if (
                trim((string) ($row[$field] ?? ''))
                !== (string) ($checkpoint[$field] ?? '')
            ) {
                return new WP_Error(
                    'nwmd_ai_state_validation_run_identity_mismatch',
                    sprintf(
                        __(
                            'operator-runs.csv does not match its queue checkpoint on line %d.',
                            'local-directory-framework'
                        ),
                        $row_number
                    )
                );
            }
        }
    }

    $package['relationship_counts'] = [
        'businesses' =>
            count($business_lookup),
        'sources' =>
            count($source_lookup),
        'deals' =>
            count($deal_lookup),
        'jobs' =>
            count($job_lookup),
        'checkpoints' =>
            count($checkpoint_lookup),
        'runs' =>
            count($run_lookup),
        'taxonomy_terms' =>
            count($taxonomy_lookup),
        'states' =>
            $taxonomy_counts['nwmd_state'],
        'cities' =>
            $taxonomy_counts['nwmd_city'],
        'categories' =>
            $taxonomy_counts['nwmd_category'],
        'specialties' =>
            $taxonomy_counts['nwmd_specialty'],
    ];

    return $package;
}

/**
 * Read and fully validate one uploaded AI state ZIP.
 *
 * This function is read-only.
 *
 * @param string $zip_path Uploaded ZIP path.
 *
 * @return array|WP_Error
 */
function nwmd_directory_validate_ai_state_zip($zip_path) {

    $files =
        nwmd_directory_read_ai_state_zip_files(
            $zip_path
        );

    if (is_wp_error($files)) {
        return $files;
    }

    $package =
        nwmd_directory_validate_ai_state_package_structure(
            $files
        );

    if (is_wp_error($package)) {
        return $package;
    }

    return nwmd_directory_validate_ai_state_record_relationships(
        $package
    );
}

/**
 * Return the current user's AI state validation result key.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_validation_result_key() {

    return 'nwmd_ai_state_validation_'
        . get_current_user_id();
}

/**
 * Store a short-lived AI state validation result.
 *
 * @param array $result Validation result.
 */
function nwmd_directory_store_ai_state_validation_result(
    array $result
) {

    set_transient(
        nwmd_directory_get_ai_state_validation_result_key(),
        $result,
        5 * MINUTE_IN_SECONDS
    );
}

/**
 * Read and remove the current user's validation result.
 *
 * @return array
 */
function nwmd_directory_take_ai_state_validation_result() {

    $key    =
        nwmd_directory_get_ai_state_validation_result_key();
    $result = get_transient($key);

    delete_transient($key);

    return is_array($result)
        ? $result
        : [];
}

/**
 * Redirect back to the Data Operator page.
 */
function nwmd_directory_redirect_ai_state_validation() {

    $url = add_query_arg(
        [
            'post_type' =>
                'nwmd_business',
            'page' =>
                'nwmd-data-operator',
            'ai_state_validation' =>
                '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Store a validation result and redirect.
 *
 * @param array $result Validation result.
 */
function nwmd_directory_finish_ai_state_validation(
    array $result
) {

    nwmd_directory_store_ai_state_validation_result(
        $result
    );

    nwmd_directory_redirect_ai_state_validation();
}

/**
 * Render the latest AI state validation result.
 */
function nwmd_directory_render_ai_state_validation_notice() {

    if (
        !isset($_GET['ai_state_validation'])
        || '1' !== sanitize_text_field(
            wp_unslash($_GET['ai_state_validation'])
        )
    ) {
        return;
    }

    $result =
        nwmd_directory_take_ai_state_validation_result();

    if (empty($result)) {
        return;
    }

    if (empty($result['success'])) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong>
                    <?php
                    echo esc_html__(
                        'AI state validation failed:',
                        'local-directory-framework'
                    );
                    ?>
                </strong>
                <?php
                echo esc_html(
                    (string) (
                        $result['message']
                        ?? __(
                            'The AI state ZIP could not be validated.',
                            'local-directory-framework'
                        )
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    $summary = isset($result['summary'])
        && is_array($result['summary'])
            ? $result['summary']
            : [];

    $counts = isset($summary['counts'])
        && is_array($summary['counts'])
            ? $summary['counts']
            : [];

    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <strong>
                <?php
                echo esc_html__(
                    'AI state ZIP passed validation.',
                    'local-directory-framework'
                );
                ?>
            </strong>
            <?php
            echo esc_html(
                (string) ($summary['filename'] ?? '')
            );
            ?>
        </p>

        <p>
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'Format %1$s | Export plugin %2$s | Generated %3$s',
                        'local-directory-framework'
                    ),
                    (string) (
                        $summary['format_version'] ?? ''
                    ),
                    (string) (
                        $summary['plugin_version'] ?? ''
                    ),
                    (string) (
                        $summary['generated_at_utc'] ?? ''
                    )
                )
            );
            ?>
        </p>

        <p>
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'Businesses %1$d | Sources %2$d | Deals %3$d | Jobs %4$d | Checkpoints %5$d | Runs %6$d | Taxonomy terms %7$d',
                        'local-directory-framework'
                    ),
                    absint($counts['businesses'] ?? 0),
                    absint($counts['sources'] ?? 0),
                    absint($counts['deals'] ?? 0),
                    absint($counts['jobs'] ?? 0),
                    absint($counts['checkpoints'] ?? 0),
                    absint($counts['runs'] ?? 0),
                    absint($counts['taxonomy_terms'] ?? 0)
                )
            );
            ?>
        </p>

        <p>
            <?php
            echo esc_html__(
                'This was a read-only validation. No directory, Deal, queue, taxonomy, or Operator data was imported or changed.',
                'local-directory-framework'
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Validate an uploaded AI state ZIP without importing it.
 */
function nwmd_directory_handle_ai_state_validation() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to validate AI state data.',
                'local-directory-framework'
            ),
            esc_html__(
                'Access denied',
                'local-directory-framework'
            ),
            [
                'response' => 403,
            ]
        );
    }

    check_admin_referer(
        'nwmd_directory_validate_ai_state'
    );

    $file = isset($_FILES['nwmd_ai_state_zip'])
        && is_array($_FILES['nwmd_ai_state_zip'])
            ? $_FILES['nwmd_ai_state_zip']
            : null;

    if (!is_array($file)) {
        nwmd_directory_finish_ai_state_validation(
            [
                'success' => false,
                'message' => __(
                    'Select an AI state ZIP to validate.',
                    'local-directory-framework'
                ),
            ]
        );
    }

    $upload_error = isset($file['error'])
        ? absint($file['error'])
        : UPLOAD_ERR_NO_FILE;

    if (UPLOAD_ERR_OK !== $upload_error) {
        $messages = [
            UPLOAD_ERR_INI_SIZE =>
                __(
                    'The ZIP exceeds the server upload limit.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_FORM_SIZE =>
                __(
                    'The ZIP exceeds the form upload limit.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_PARTIAL =>
                __(
                    'The ZIP upload was incomplete.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_NO_FILE =>
                __(
                    'Select an AI state ZIP to validate.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_NO_TMP_DIR =>
                __(
                    'The server upload directory is unavailable.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_CANT_WRITE =>
                __(
                    'The server could not write the uploaded ZIP.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_EXTENSION =>
                __(
                    'A server extension stopped the ZIP upload.',
                    'local-directory-framework'
                ),
        ];

        nwmd_directory_finish_ai_state_validation(
            [
                'success' => false,
                'message' =>
                    $messages[$upload_error]
                    ?? __(
                        'The AI state ZIP upload failed.',
                        'local-directory-framework'
                    ),
            ]
        );
    }

    $filename = isset($file['name'])
        ? sanitize_file_name(
            wp_unslash((string) $file['name'])
        )
        : '';

    $file_size = isset($file['size'])
        ? absint($file['size'])
        : 0;

    $tmp_name = isset($file['tmp_name'])
        ? (string) $file['tmp_name']
        : '';

    if (
        'zip' !== strtolower(
            (string) pathinfo(
                $filename,
                PATHINFO_EXTENSION
            )
        )
    ) {
        nwmd_directory_finish_ai_state_validation(
            [
                'success' => false,
                'message' => __(
                    'The uploaded file must use the .zip extension.',
                    'local-directory-framework'
                ),
            ]
        );
    }

    if ($file_size < 1) {
        nwmd_directory_finish_ai_state_validation(
            [
                'success' => false,
                'message' => __(
                    'The uploaded ZIP is empty.',
                    'local-directory-framework'
                ),
            ]
        );
    }

    if ($file_size > 10 * MB_IN_BYTES) {
        nwmd_directory_finish_ai_state_validation(
            [
                'success' => false,
                'message' => __(
                    'The uploaded ZIP exceeds the 10 MB limit.',
                    'local-directory-framework'
                ),
            ]
        );
    }

    if (
        '' === $tmp_name
        || !is_uploaded_file($tmp_name)
        || !is_readable($tmp_name)
    ) {
        nwmd_directory_finish_ai_state_validation(
            [
                'success' => false,
                'message' => __(
                    'The uploaded ZIP could not be read safely.',
                    'local-directory-framework'
                ),
            ]
        );
    }

    $validated =
        nwmd_directory_validate_ai_state_zip(
            $tmp_name
        );

    if (is_wp_error($validated)) {
        nwmd_directory_finish_ai_state_validation(
            [
                'success' => false,
                'message' => sanitize_text_field(
                    $validated->get_error_message()
                ),
            ]
        );
    }

    $manifest = isset($validated['manifest'])
        && is_array($validated['manifest'])
            ? $validated['manifest']
            : [];

    $counts = isset($validated['relationship_counts'])
        && is_array($validated['relationship_counts'])
            ? $validated['relationship_counts']
            : [];

    nwmd_directory_finish_ai_state_validation(
        [
            'success' => true,
            'summary' => [
                'filename' =>
                    $filename,
                'format_version' =>
                    (string) (
                        $manifest['format_version'] ?? ''
                    ),
                'plugin_version' =>
                    (string) (
                        $manifest['plugin_version'] ?? ''
                    ),
                'generated_at_utc' =>
                    (string) (
                        $manifest['generated_at_utc'] ?? ''
                    ),
                'source_site' =>
                    (string) (
                        $manifest['source_site'] ?? ''
                    ),
                'counts' =>
                    $counts,
            ],
        ]
    );
}

add_action(
    'admin_post_nwmd_directory_validate_ai_state',
    'nwmd_directory_handle_ai_state_validation'
);
