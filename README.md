# WP AyeCode Template Manager

A centralized template management system for the AyeCode WordPress plugin ecosystem. This package is designed to be integrated as a Composer dependency into AyeCode plugins (GeoDirectory, UsersWP, Invoicing) to provide a unified interface for managing and displaying plugin templates to users.

## Overview

The Template Manager provides:

- **Centralized Template Storage**: Custom post type (`ayecode_template`) for storing templates across multiple page builders
- **Multi-Builder Support**: Compatible with Gutenberg, Elementor, Beaver Builder, and Divi
- **Product Integration**: Templates can be assigned to specific AyeCode products
- **Status Management**: Templates can be marked as Active, Draft, Customized, or Default
- **Admin Interface**: Built on the AyeCode Settings Framework with a filterable list table interface

## Features

- Hidden custom post type for backend template storage
- Settings Framework integration for user-friendly template management
- AJAX-powered CRUD operations for templates
- Thumbnail/preview support for templates
- Bulk actions (delete, activate, set to draft)
- Status filtering and organization
- Product-specific template filtering via hooks

## Usage as Composer Package

This package is intended to be pulled into AyeCode WordPress plugins via Composer and used to display plugin templates to users. The package provides hooks and filters allowing individual plugins to extend and customize the template management interface for their specific needs.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- AyeCode Settings Framework