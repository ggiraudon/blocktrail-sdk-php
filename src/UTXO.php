<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Script\ScriptFactory;
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

    /**
     * Returns an estimation of the inputs scriptSig
     * and witness size, in bytes. Witness size will
     * be zero if no witness,
     *
     * @return array
     */
    public function estimateInputSize()
    {
        $classifier = new OutputClassifier();
        $decodePK = $classifier->decode($this->scriptPubKey);

        $witness = false;
        if ($decodePK->getType() === ScriptType::P2SH) {
            if (null === $this->redeemScript) {
                throw new \RuntimeException("Can't estimate, missing redeem script");
            }
            $decodePK = $classifier->decode($this->redeemScript);
        }

        if ($decodePK->getType() === ScriptType::P2WKH) {
            $scriptSitu = ScriptFactory::scriptPubKey()->p2pkh($decodePK->getSolution());
            $decodePK = $classifier->decode($scriptSitu);
            $witness = true;
        } else if ($decodePK->getType() === ScriptType::P2WSH) {
            if (null === $this->witnessScript) {
                throw new \RuntimeException("Can't estimate, missing witness script");
            }
            $decodePK = $classifier->decode($this->witnessScript);
            $witness = true;
        }

        if (!in_array($decodePK->getType(), [ScriptType::MULTISIG, ScriptType::P2PKH, ScriptType::P2PK])) {
            throw new \RuntimeException("Unsupported script type");
        }

        $script = $decodePK->getScript();
        list ($scriptSig, $witness) = SizeEstimation::estimateInputSize($script, $this->redeemScript, $this->witnessScript, $witness);

        return [
            "scriptSig" => $scriptSig,
            "witness" => $witness,
        ];
    }
}
