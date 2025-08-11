<?php

namespace App\Notifications;

use App\Models\Dividend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class DividendAlertNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Dividend $dividend)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->dividend->company;

        return (new MailMessage)
            ->subject("📈 {$c->name_kr} 배당 공시")
            ->line("현금배당 금액: ₩".number_format($this->dividend->cash_amount))
            ->line("기준일: {$this->dividend->record_date}")
            ->line("배당락일: {$this->dividend->ex_dividend_date}")
            ->line("지급예정일: {$this->dividend->payment_date}")
            ->action('상세 보기', url('/'))   // 추후 상세 페이지로 변경
            ->line('※ 본 알림은 투자 권유가 아닙니다.');
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $c = $this->dividend->company;

        $telegram =TelegramMessage::create()
            ->to(config('services.telegram-bot-api.chat_id'))
            ->content("💰 *{$c->name_kr}* 배당 공시\n"
                ."금액: ₩".number_format($this->dividend->cash_amount)."\n"
                ."기준일: {$this->dividend->record_date}");

        if (app()->environment('production')) {
            $telegram->button('상세 보기', config('app.url'));
        }

        return $telegram;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
