Very long text field

This module provides a custom field type that stores very large text using a database big BLOB column.

Provided plugins:
- Field type: `Very long text` (machine name `very_long_text`).
- Widget: `Very long text area` (multi-line textarea).
- Formatter: `Plain text` (with optional newline to <br> conversion).

Installation
- Enable the module: Extend → search for "Very long text field" and enable it, or run `drush en very_long_text -y`.

Usage
1) Add a new field to a content type (or any fieldable entity):
   - Field type: Very long text.
   - Choose the `Very long text area` widget.
2) Optionally configure the formatter to convert newlines to `<br>`.

Notes
- Storage uses a big BLOB column suitable for very large content.
- Output is HTML-escaped by the formatter for safety.
