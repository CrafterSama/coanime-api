# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## 2026-10-26

### feat: Laravel 13, PHP 8.4 y scraper MAL (jikan-me/jikan)
- **Fecha:** 2026-10-26
- **Branch:** feature/CACMS8-change-versions-and-jikan
- Sube PHP a ^8.4.4 y Laravel 11→13 (tinker ^3, phpunit ^12, boost ^2, laravel-json-api ^5.3)
- Reemplaza el cliente REST `jikan/jikan-php` (api.jikan.moe cerrado) por el parser `jikan-me/jikan` que scrapea myanimelist.net
- Broadcast, search y season sync dejan de depender de Jikan público; CSRF usa PreventRequestForgery
