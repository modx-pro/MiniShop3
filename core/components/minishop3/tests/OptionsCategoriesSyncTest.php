<?php

/** @var \MiniShop3\Tests\OptionsCategoriesSyncTest $this */
class OptionsCategoriesSyncTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Option category sync must detach for the modern list payload (#869).
     *
     * The flat list payload ([id, id, ...]) is the desired set of enabled category
     * ids: an option saved with a shorter list must lose the previously linked
     * categories that are absent from it. The old implementation only added the
     * listed ids and never removed anything, so unchecking a category kept the link.
     */
    public function testOptionsCategoriesSyncDetachesForFlatListPayload(): void
    {
        $fail = static function (string $message): never {
            fwrite(STDERR, "FAIL OptionsCategoriesSyncTest: {$message}\n");
            exit(1);
        };

        $controllerPath = dirname(__DIR__, 4) . '/core/components/minishop3/src/Controllers/Api/Manager/OptionsController.php';
        if (!is_readable($controllerPath)) {
            $fail('Cannot read ' . $controllerPath);
        }

        $source = (string)file_get_contents($controllerPath);

        if (!str_contains($source, 'if ($this->isCategoriesIdListPayload($payload))')) {
            $fail('sync must branch on the flat list payload shape (#869)');
        }
        if (!str_contains($source, 'array_diff($current, $enabled)')) {
            $fail('list sync must compute detach as current links minus enabled ids (#869)');
        }

        fwrite(STDOUT, "OK OptionsCategoriesSyncTest (#869)\n");
        exit(0);
    }
}