<x-mail::message>
# Welcome to {{ config('app.name') }}, {{ $name }}!

We're glad to have you on board. {{ config('app.name') }} helps you manage your classes, track attendance, record assessments, and generate professional report cards — all in one place.

Here's what you can do right now:

<x-mail::panel>
**Get started in minutes**
- Create your first class
- Add your students
- Record attendance and assessments
- Generate and share report cards with parents
</x-mail::panel>

<x-mail::button :url="$dashboardUrl" color="primary">
Go to Dashboard
</x-mail::button>

If you have any questions, just reply to this email — we're here to help.

Thanks,<br>
The {{ config('app.name') }} Team
</x-mail::message>
