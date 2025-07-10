<?php return array(
    'root' => array(
        'name' => 'payrexx/payment',
        'pretty_version' => '2.2.0',
        'version' => '2.2.0.0',
        'reference' => null,
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'payrexx/payment' => array(
            'pretty_version' => '2.2.0',
            'version' => '2.2.0.0',
            'reference' => null,
            'type' => 'shopware-platform-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'payrexx/payrexx' => array(
            'pretty_version' => 'v2.0.1',
            'version' => '2.0.1.0',
            'reference' => '09f68c0463b1c240f9b051caf0ec305e80f3f8e3',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payrexx/payrexx',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
