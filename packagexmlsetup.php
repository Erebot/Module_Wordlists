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

foreach (array($package, $compatible) as $obj) {
    // FIXME: $package needs the original filenames,
    // while $compatible wants the logical filenames.
    if ($obj === $compatible) {
        $srcDir     = 'php';
    }
    else {
        $srcDir     = 'src';
    }

    $obj->files["$srcDir/Erebot/Module/Wordlists.php"] = array_merge_recursive(
        $obj->files["$srcDir/Erebot/Module/Wordlists.php"]->getArrayCopy(),
        $data_dir
    );
}

