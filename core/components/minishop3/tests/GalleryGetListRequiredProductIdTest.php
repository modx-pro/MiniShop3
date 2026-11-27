<?php

/**
 * #901: Gallery\GetList must report a missing product_id instead of silently
 * returning an empty list, without breaking explicit product_id = 0 queries
 * (new product / orphan files) used by the Vue gallery.
 *
 * Run: php tests/GalleryGetListRequiredProductIdTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$read = static function (string $relative) use ($fail): string {
    $path = __DIR__ . '/../' . $relative;
    $source = file_get_contents($path);
    if ($source === false) {
        $fail("Unable to read {$relative}");
    }

    return $source;
};

$processor = $read('src/Processors/Gallery/GetList.php');
if (!str_contains($processor, "getProperty('product_id', null) === null")) {
    $fail('GetList must distinguish a missing product_id key from an explicit zero (#901)');
}
if (!str_contains($processor, "return \$this->modx->lexicon('ms3_err_product_id_ns');")) {
    $fail('GetList must fail with ms3_err_product_id_ns when product_id is absent (#901)');
}
if (preg_match('/\bproduct_id[^\n]*<=\s*0/', $processor) === 1) {
    $fail('GetList must not reject product_id = 0: the Vue gallery relies on an empty list (#901)');
}

foreach (['ru', 'en'] as $language) {
    $lexicon = $read("lexicon/{$language}/default.inc.php");
    if (!str_contains($lexicon, "\$_lang['ms3_err_product_id_ns']")) {
        $fail("lexicon/{$language}/default.inc.php missing ms3_err_product_id_ns (#901)");
    }
}

// The processor is also exercised with an explicit zero; keep that contract.
$coverage = file_get_contents(__DIR__ . '/Modx/ProcessorCoverageTest.php');
if ($coverage === false || !str_contains($coverage, "GalleryGetList::class, ['product_id' => 0")) {
    $fail('ProcessorCoverageTest must keep the explicit product_id = 0 case (#901)');
}

// Vue sent `parent` and `type`, which GetList never reads. Remove the dead
// payload instead of adding filters that would change gallery behaviour.
$vue = $read('../../../vueManager/src/composables/useGalleryApi.js');
$start = strpos($vue, 'async function fetchGalleryList');
if ($start === false) {
    $fail('useGalleryApi.js missing fetchGalleryList()');
}
$end = strpos($vue, "\n  }", $start);
$body = $end === false ? substr($vue, $start) : substr($vue, $start, $end - $start);

if (!str_contains($body, 'product_id: productId')) {
    $fail('fetchGalleryList() must send product_id (#901)');
}
if (preg_match('/\bparent\s*:/', $body) === 1 || preg_match('/\btype\s*:/', $body) === 1) {
    $fail('fetchGalleryList() must not send unused parent/type params (#901)');
}

fwrite(STDOUT, "OK GalleryGetListRequiredProductIdTest (#901)\n");
exit(0);
