<?php
$config = new PhpCsFixer\Config();

return $config->setRules([
    '@PSR12' => true,
])
->setCacheFile(__DIR__ . '/../cache/.php_cs.cache')
->setFinder(
  PhpCsFixer\Finder::create()
                   ->in(__DIR__ . '/../../src/')
                   ->in(__DIR__ . '/../../tests/')
);