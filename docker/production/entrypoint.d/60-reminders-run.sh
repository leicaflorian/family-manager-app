#!/bin/sh
# After the migrations and the caches of the Laravel automations (50-laravel-automations), run the reminders
# once: it catches up a day the server was down. The command holds Cache::lock('reminders:run'), so it never
# sends twice if the scheduler's 6:00 run overlaps. A failure is logged but does not stop the container.
if [ "$AUTORUN_ENABLED" = "true" ]; then
    echo "🔔 Running the reminders once: \"php artisan reminders:run\"..."
    php /var/www/html/artisan reminders:run || echo "⚠️ reminders:run failed at start; the scheduler runs it again at 6:00."
fi
