<?php
// # PayByBankApp

// To include the necessary files, we use the composer for PSR-4 autoloading.
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../inc/common.php';
require __DIR__ . '/../inc/config.php';

use Wirecard\PaymentSdk\Entity\Amount;
use Wirecard\PaymentSdk\Entity\Redirect;
use Wirecard\PaymentSdk\Entity\Device;
use Wirecard\PaymentSdk\Entity\CustomField;
use Wirecard\PaymentSdk\Entity\CustomFieldCollection;
use Wirecard\PaymentSdk\Response\FailureResponse;
use Wirecard\PaymentSdk\Response\InteractionResponse;
use Wirecard\PaymentSdk\Transaction\PayByBankAppTransaction;
use Wirecard\PaymentSdk\TransactionService;
  
$amount = new Amount(1.23, 'GBP');

$transaction = new PayByBankAppTransaction();
$transaction->setAmount($amount);

$device = new Device();
$device->setType("pc");
$device->setOperatingSystem("windows");
$transaction->setDevice($device);

$customFields = new CustomFieldCollection();
$transaction->setCustomFields($customFields);

function addCustomField($key, $value) {
    $customField = new CustomField($key, $value);
    $customField->setPrefix("");
    return $customField;
}

$customFields->add(addCustomField('zapp.in.MerchantRtnStrng', '123'));
$customFields->add(addCustomField('zapp.in.TxType', 'PAYMT'));
$customFields->add(addCustomField('zapp.in.DeliveryType', 'DELTAD'));

// The redirect URLs determine where the consumer should be redirected after approval/cancellation.
$redirectUrls = new Redirect(getUrl('return.php?status=success'), getUrl('return.php?status=cancel'));
$transaction->setRedirect($redirectUrls);

// As soon as the transaction status changes, a server-to-server notification will get delivered to this URL.
$notificationUrl = getUrl('notify.php');

$transaction->setNotificationUrl($notificationUrl);

$transactionService = new TransactionService($config);      
$response = $transactionService->pay($transaction);

// ## Response handling
// The response from the service can be used for disambiguation.
// Since a redirect for successful transactions is defined, a InteractionResponse is returned
// if the transaction was successful.
if ($response instanceof InteractionResponse) {
        die("<meta http-equiv='refresh' content='0;url={$response->getRedirectUrl()}'>");
// In case of a failed transaction, a `FailureResponse` object is returned.
} elseif ($response instanceof FailureResponse) {
    $jresult = ['success' => false, 'errors' => []];

    foreach ($response->getStatusCollection() as $status) {
        /**
         * @var $status \Wirecard\PaymentSdk\Entity\Status
         */
        $severity = ucfirst($status->getSeverity());
        $code = $status->getCode();
        $description = $status->getDescription();
        
        $err = ['severity' => $severity, 'code' => $code, 'description' => $description];
        $jresult['errors'][] = $err;
    }

    $resultJson = json_encode($jresult);
    echo($resultJson);
}       
?>
