<?php

// Re-use the default packagexmlsetup.php from the build environment.
require(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'buildenv' .
    DIRECTORY_SEPARATOR . 'packagexmlsetup.php'
);

// And do some substitutions while we're at it.
$data_dir = array(
    'tasks:replace' => array(
        'attribs' => array(
            'from'  => '@data_dir@',
            'to'    => 'data_dir',
            'type'  => 'pear-config'
        )
    )
);

$package->files["src/Erebot/Module/Wordlists.php"] = array_merge_recursive(
    $package->files["src/Erebot/Module/Wordlists.php"]->getArrayCopy(),
    $data_dir
);

