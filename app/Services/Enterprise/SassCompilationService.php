<?php

namespace App\Services\Enterprise;

use App\Models\WhiteLabelConfig;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

/**
 * Service for compiling SASS for dynamic branding.
 */
class SassCompilationService
{
    private Compiler $compiler;

    public function __construct()
    {
        $this->compiler = new Compiler;
        $this->compiler->setOutputStyle(OutputStyle::COMPRESSED);
        // Set import paths to allow `@import 'variables';`
        $this->compiler->setImportPaths(resource_path('sass/branding'));
    }

    /**
     * Compiles the main branding SASS file with theme variables.
     *
     * @param  WhiteLabelConfig  $config  The white-label configuration.
     * @return string The compiled CSS.
     *
     * @throws \Exception If the SASS template file is not found or compilation fails.
     */
    public function compile(WhiteLabelConfig $config): string
    {
        $templatePath = resource_path('sass/branding/theme.scss');
        if (! File::exists($templatePath)) {
            throw new \Exception("SASS template not found at {$templatePath}");
        }

        $sassVariables = $this->generateSassVariables($config->theme_config);
        $sassInput = $sassVariables."\n".File::get($templatePath);

        try {
            return $this->compiler->compileString($sassInput)->getCss();
        } catch (\Exception $e) {
            Log::error('SASS compilation failed.', ['error' => $e->getMessage()]);
            throw new \Exception('SASS compilation failed.', 0, $e);
        }
    }

    /**
     * Compiles the dark mode SASS file.
     *
     * @return string The compiled dark mode CSS.
     *
     * @throws \Exception If the dark mode SASS file is not found or compilation fails.
     */
    public function compileDarkMode(): string
    {
        $darkModePath = resource_path('sass/branding/dark.scss');
        if (! File::exists($darkModePath)) {
            throw new \Exception("Dark mode SASS file not found at {$darkModePath}");
        }

        try {
            return $this->compiler->compileString(File::get($darkModePath))->getCss();
        } catch (\Exception $e) {
            Log::error('Dark mode SASS compilation failed.', ['error' => $e->getMessage()]);
            throw new \Exception('Dark mode SASS compilation failed.', 0, $e);
        }
    }

    /**
     * Generates a SASS-compatible variable string from a theme config array.
     */
    private function generateSassVariables(?array $themeConfig): string
    {
        if (empty($themeConfig)) {
            return '';
        }

        $sassLines = [];
        foreach ($themeConfig as $key => $value) {
            if (is_string($value) && ! empty($value)) {
                // Format key from snake_case to kebab-case for SASS variable
                $sassKey = str_replace('_', '-', $key);
                $sassLines[] = "\${$sassKey}: ".$this->formatSassValue($value).';';
            }
        }

        return implode("\n", $sassLines);
    }

    /**
     * Formats a value for use in a SASS variable declaration.
     * Ensures colors are treated as literals and other strings are quoted if necessary.
     * @throws \Exception
     */
    private function formatSassValue(string $value): string
    {
        $value = trim($value);

        // If it's a hex, rgb, rgba, hsl, hsla, or a CSS variable, return as is.
        if (
            preg_match('/^#([a-f0-9]{3}){1,2}$/i', $value) ||
            preg_match('/^(rgb|rgba|hsl|hsla)\(/i', $value) ||
            preg_match('/^var\(--.*\)$/i', $value)
        ) {
            return $value;
        }

        // If it's a named color, it's also a valid literal
        $namedColors = ['transparent', 'currentColor', 'white', 'black', 'red', 'blue']; // Add more if needed
        if (in_array(strtolower($value), $namedColors)) {
            return $value;
        }

        // For font families or other string values that might contain spaces,
        // quote them if they aren't already.
        if (str_contains($value, ' ') && ! preg_match('/^".*"$/', $value) && ! preg_match("/^'.*'$/", $value)) {
            return '"'.$value.'"';
        }

        if (preg_match('/^[a-zA-Z0-9 #,.-]+$/', $value)) {
            return $value;
        }

        throw new \Exception("Invalid SASS value: {$value}");
    }
}
