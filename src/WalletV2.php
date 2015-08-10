<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;


use BitWasp\BitcoinLib\BIP39\BIP39;
use Blocktrail\CryptoJSAES\CryptoJSAES;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Exceptions\WalletDecryptException;

class WalletV2 extends Wallet {

    protected $encryptedPrimarySeed;

    protected $encryptedSecret;

    protected $secret = null;

    protected $primarySeed = null;

    /**
     * @param BlocktrailSDKInterface $sdk        SDK instance used to do requests
     * @param string                 $identifier identifier of the wallet
     * @param string                 $encryptedPrimarySeed
     * @param                        $encryptedSecret
     * @param                        $primaryPublicKeys
     * @param                        $backupPublicKey
     * @param array                  $blocktrailPublicKeys
     * @param int                    $keyIndex
     * @param string                 $network
     * @param bool                   $testnet
     * @param string                 $checksum
     */
    public function __construct(BlocktrailSDKInterface $sdk, $identifier, $encryptedPrimarySeed, $encryptedSecret, $primaryPublicKeys, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $network, $testnet, $checksum) {
        $this->encryptedPrimarySeed = $encryptedPrimarySeed;
        $this->encryptedSecret = $encryptedSecret;

        parent::__construct($sdk, $identifier, $primaryPublicKeys, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $network, $testnet, $checksum);
    }

    /**
     * unlock wallet so it can be used for payments
     *
     * @param          $options ['primary_private_key' => key] OR ['passphrase' => pass]
     * @param callable $fn
     * @return bool
     * @throws \Exception
     */
    public function unlock($options, callable $fn = null) {
        // explode the wallet data
        $password = isset($options['passphrase']) ? $options['passphrase'] : (isset($options['password']) ? $options['password'] : null);
        $encryptedPrimarySeed = $this->encryptedPrimarySeed;
        $encryptedSecret = $this->encryptedSecret;
        $primaryPrivateKey = isset($options['primary_private_key']) ? $options['primary_private_key'] : null;

        if (isset($options['secret'])) {
            $this->secret = $options['secret'];
        }
        if (isset($options['primary_seed'])) {
            $this->primarySeed = $options['primary_seed'];
        }

        if (!$primaryPrivateKey) {
            if (!$password) {
                throw new \InvalidArgumentException("Can't init wallet with Primary Seed without a passphrase");
            } else if (!$encryptedSecret) {
                throw new \InvalidArgumentException("Can't init wallet with Primary Seed without a encrypted secret");
            }
        }

        if ($primaryPrivateKey) {
            if (is_string($primaryPrivateKey)) {
                $primaryPrivateKey = [$primaryPrivateKey, "m"];
            }
        } else {
            if (!($this->secret = CryptoJSAES::decrypt($encryptedSecret, $password))) {
                throw new WalletDecryptException("Failed to decrypt secret with password");
            }

            // convert the mnemonic to a seed using BIP39 standard
            if (!($this->primarySeed = CryptoJSAES::decrypt($encryptedPrimarySeed, $this->secret))) {
                throw new WalletDecryptException("Failed to decrypt primary seed with secret");
            }

            // create BIP32 private key from the seed
            $primaryPrivateKey = BIP32::master_key(bin2hex(base64_decode($this->primarySeed)), $this->network, $this->testnet);
        }

        $this->primaryPrivateKey = BIP32Key::create($primaryPrivateKey);

        // create checksum (address) of the primary privatekey to compare to the stored checksum
        $checksum = BIP32::key_to_address($primaryPrivateKey[0]);
        if ($checksum != $this->checksum) {
            throw new \Exception("Checksum [{$checksum}] does not match [{$this->checksum}], most likely due to incorrect password");
        }

        $this->locked = false;

        // if the response suggests we should upgrade to a different blocktrail cosigning key then we should
        if (isset($data['upgrade_key_index'])) {
            $this->upgradeKeyIndex($data['upgrade_key_index']);
        }

        if ($fn) {
            $fn($this);
            $this->lock();
        }
    }

    /**
     * lock the wallet (unsets primary private key)
     *
     * @return void
     */
    public function lock() {
        $this->primaryPrivateKey = null;
        $this->secret = null;
        $this->primarySeed = null;
        $this->locked = true;
    }

    /**
     * change password that is used to store data encrypted on server
     *
     * @param $newPassword
     * @return array backupInfo
     * @throws BlocktrailSDKException
     */
    public function passwordChange($newPassword) {
        if ($this->locked) {
            throw new BlocktrailSDKException("Wallet needs to be unlocked to change password");
        }

        if (!$this->secret) {
            throw new BlocktrailSDKException("No secret");
        }

        $encryptedSecret = CryptoJSAES::encrypt($this->secret, $newPassword);

        $this->sdk->updateWallet($this->identifier, ['encrypted_secret' => $encryptedSecret]);

        $this->encryptedSecret = $encryptedSecret;

        return [
            'encrypted_secret' => BIP39::entropyToMnemonic(bin2hex(base64_decode($this->encryptedSecret))),
        ];
    }
}