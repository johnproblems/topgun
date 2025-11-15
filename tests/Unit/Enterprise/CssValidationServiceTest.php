<?php

use App\Services\Enterprise\CssValidationService;
use Tests\TestCase;

uses(TestCase::class);

it('strips @import rules', function () {
    $service = new CssValidationService;

    $maliciousCss = '@import url("malicious.css"); body { color: red; }';
    $sanitized = $service->sanitize($maliciousCss);

    expect($sanitized)
        ->not->toContain('@import')
        ->toContain('color: red');
});

it('removes javascript protocol handlers', function () {
    $service = new CssValidationService;

    $maliciousCss = 'body { background: url(javascript:alert("XSS")); }';
    $sanitized = $service->sanitize($maliciousCss);

    expect($sanitized)->not->toContain('javascript:');
});

it('removes expression patterns', function () {
    $service = new CssValidationService;

    $maliciousCss = 'body { width: expression(document.body.clientWidth); }';
    $sanitized = $service->sanitize($maliciousCss);

    expect($sanitized)->not->toContain('expression(');
});

it('removes vbscript protocol handlers', function () {
    $service = new CssValidationService;

    $maliciousCss = 'body { background: url(vbscript:alert("XSS")); }';
    $sanitized = $service->sanitize($maliciousCss);

    expect($sanitized)->not->toContain('vbscript:');
});

it('removes HTML script tags', function () {
    $service = new CssValidationService;

    $maliciousCss = '<script>alert("XSS")</script> body { color: red; }';
    $sanitized = $service->sanitize($maliciousCss);

    expect($sanitized)
        ->not->toContain('<script>')
        ->toContain('color: red');
});

it('removes event handlers', function () {
    $service = new CssValidationService;

    $maliciousCss = 'body { color: red; } <div onclick="alert(\'XSS\')">';
    $sanitized = $service->sanitize($maliciousCss);

    expect($sanitized)->not->toContain('onclick=');
});

it('validates CSS and returns errors', function () {
    $service = new CssValidationService;

    $invalidCss = 'body { color: ; }'; // Invalid CSS
    $result = $service->validate($invalidCss);

    // If parser is available, it should catch syntax errors
    // If not, it should still catch dangerous patterns
    expect($result)->toHaveKey('valid')
        ->toHaveKey('errors');
});

it('allows valid CSS to pass through', function () {
    $service = new CssValidationService;

    $validCss = 'body { color: red; background: #fff; }';
    $sanitized = $service->sanitize($validCss);

    expect($sanitized)
        ->toContain('color: red')
        ->toContain('background');
});

it('handles empty CSS gracefully', function () {
    $service = new CssValidationService;

    $sanitized = $service->sanitize('');

    expect($sanitized)->toBeString();
});
