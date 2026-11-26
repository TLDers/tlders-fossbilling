#!/usr/bin/env php
<?php
// Generates the FOSSBilling and ClientFence modules from template/ + core/Core.php.
// Run: php build.php   (output: fossbilling/Tldersprices and clientfence/Tldersprices)

$targets = [
    'fossbilling' => [
        '__MOD_NS__' => 'Box\\Mod',
        '__API_BASE__' => '\\FOSSBilling\\Api\\AbstractApi',
        '__API_COMPAT__' => "// FOSSBilling 0.8.9 uses \\FOSSBilling\\Api\\AbstractApi; the development branch renamed it.\nif (!class_exists('\\FOSSBilling\\Api\\AbstractApi') && class_exists('\\FOSSBilling\\Core\\Api\\AbstractApi')) {\n    class_alias('\\FOSSBilling\\Core\\Api\\AbstractApi', '\\FOSSBilling\\Api\\AbstractApi');\n}\n",
        '__DI_IFACE__' => '\\FOSSBilling\\InjectionAwareInterface',
        '__APP__' => '\\Box_App',
    ],
    'clientfence' => [
        '__MOD_NS__' => 'ClientFence\\Mod',
        '__API_BASE__' => '\\ClientFence\\Api\\AbstractApi',
        '__API_COMPAT__' => '',
        '__DI_IFACE__' => '\\ClientFence\\InjectionAwareInterface',
        '__APP__' => '\\ClientFence\\App',
    ],
];

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

$root = __DIR__;
foreach ($targets as $name => $tokens) {
    $out = "$root/$name/Tldersprices";
    rrmdir("$root/$name");
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/template", FilesystemIterator::SKIP_DOTS)) as $file) {
        $rel = substr($file->getPathname(), strlen("$root/template/"));
        $dest = "$out/$rel";
        @mkdir(dirname($dest), 0777, true);
        file_put_contents($dest, strtr(file_get_contents($file->getPathname()), $tokens));
    }
    copy("$root/core/Core.php", "$out/Core.php");
    echo "built $name/Tldersprices\n";
}
