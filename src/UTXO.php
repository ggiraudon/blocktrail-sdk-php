<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\AddressInterface;
use BitWasp\Bitcoin\Script\ScriptInterface;

class UTXO {

    public $hash;
    public $index;
    public $value;

    /**
     * @var AddressInterface
     */
    public $address;

    /**
     * @var ScriptInterface
     */
    public $scriptPubKey;
    public $path;

    /**
     * @var ScriptInterface
     */
    public $redeemScript;

    /**
     * @var null
     */
    public $witnessScript;

    public function __construct($hash, $index, $value = null, AddressInterface $address = null, ScriptInterface $scriptPubKey = null, $path = null, ScriptInterface $redeemScript = null, ScriptInterface $witnessScript = null) {
        $this->hash = $hash;
        $this->index = $index;
        $this->value = $value;
        $this->address = $address;
        $this->scriptPubKey = $scriptPubKey;
        $this->path = $path;
        $this->redeemScript = $redeemScript;
        $this->witnessScript = $witnessScript;
    }
}
