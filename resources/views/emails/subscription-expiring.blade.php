<x-mail::message>
# Your Subscription is Expiring Soon

Hi {{ $name }},

Your **{{ $planName }}** subscription on {{ config('app.name') }} expires in **{{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}** on **{{ $expiresAt }}**.

To keep access to your classes, student records, assessments, and report cards, please renew before it expires.

<x-mail::button :url="$renewUrl" color="primary">
Renew My Subscription
</x-mail::button>

<x-mail::panel>
**What happens when it expires?**

You will lose access to the app until you subscribe to a new plan. Your data is safely kept and will be available again once you renew.
</x-mail::panel>

If you have any questions about billing or your account, just reply to this email.

Thanks,<br>
The {{ config('app.name') }} Team
</x-mail::message>
