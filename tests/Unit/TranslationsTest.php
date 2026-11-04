<?php

namespace Javaabu\Imports\Tests\Unit;

use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TranslationsTest extends TestCase
{
    #[Test]
    public function every_string_has_a_dhivehi_translation(): void
    {
        $dv = json_decode(file_get_contents(__DIR__ . '/../../lang/dv.json'), true);
        $en = json_decode(file_get_contents(__DIR__ . '/../../lang/en.json'), true);

        $missing = array_values(array_diff($this->translatableStrings(), array_keys($dv)));

        $this->assertEquals([], $missing, 'Add these to lang/dv.json');
        $this->assertEquals(array_keys($dv), array_keys($en), 'lang/en.json and lang/dv.json should have the same keys');
    }

    #[Test]
    public function it_translates_into_dhivehi(): void
    {
        app()->setLocale('dv');

        $this->assertEquals('ނިމިއްޖެ', ImportStatuses::COMPLETED->label());
        $this->assertEquals('އަމިއްލަ އިމްޕޯޓްތައް ބެލުމާއި ފެށުން', __('View and start own imports'));
        $this->assertEquals('"ސޮނީ" އާ ދިމާވާ ބްރޭންޑް އެއް ނެތް.', __('No :attribute matches ":value".', ['attribute' => 'ބްރޭންޑް', 'value' => 'ސޮނީ']));
    }

    /**
     * Literal strings passed to __() in src and resources, outside comments
     *
     * @return array<int, string>
     */
    protected function translatableStrings(): array
    {
        $strings = [];

        foreach (['src', 'resources/views', 'resources/js'] as $directory) {
            $path = __DIR__ . "/../../{$directory}";

            if (! is_dir($path)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                foreach (file($file->getPathname()) as $line) {
                    if (preg_match('/^\s*(\*|\/\/|\/\*)/', $line)) {
                        continue;
                    }

                    preg_match_all("/(?<![\w>])(?:__|t)\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $line, $matches);

                    foreach ($matches[1] as $string) {
                        $strings[] = stripslashes($string);
                    }
                }
            }
        }

        foreach (['View and start own imports', "View other users' imports", 'Cancel and delete own imports', "Cancel and delete other users' imports", 'Imports'] as $permission) {
            $strings[] = $permission;
        }

        return array_values(array_unique($strings));
    }
}
