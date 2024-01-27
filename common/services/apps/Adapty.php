<?php declare(strict_types=1);

namespace common\services\apps;

use common\contracts\Notifier;
use common\services\notifier\Notification;
use yii\helpers\Inflector;

class Adapty
{
    private $notifier;

    public function __construct(Notifier $notifier)
    {
        $this->notifier = $notifier;
    }

    public function redirectEventToTelegram(array $rawEvent)
    {
        if (isset($rawEvent['adapty_check'])) {
            return [
                'adapty_check_response' => $rawEvent['adapty_check']
            ];
        }

        if (!$rawEvent['event_type']) {
            return;
        }

        $notification = new Notification(
            null,
            null,
            false,
            false,
            $rawEvent['event_properties']['store'] === 'app_store' ? 'ios' : 'android'
        );
        $notification->addLine('<b>' . Inflector::humanize($rawEvent['event_type']) . '</b>, ' . $rawEvent['event_properties']['store_country']);
        $notification->addLine('<b>Product ID</b>: ' . $rawEvent['event_properties']['vendor_product_id']);
        if ($creative = $rawEvent['attributions']['apple_search_ads']['creative'] ?? null) {
            $notification->addLine('<b>Creative:</b> ' . $creative);
        }
        $notification->addLine('<b>User ID</b>: <a href="https://app.adapty.io/profiles/users/' . $rawEvent['profile_id'] . '/">' . $rawEvent['customer_user_id'] . '</a>');
        if ($rawEvent['event_type'] !== 'trial_started') {
            $notification->addLine('<b>Revenue</b>: $' . $rawEvent['event_properties']['price_usd']);
        }
        $this->notifier->sendNotification($notification);

        return true;
    }
}