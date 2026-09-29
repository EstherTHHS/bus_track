import { from12Hour, nowTime, to12Hour, type Meridiem } from '@/lib/dates';
import { cn } from '@/lib/utils';

const MERIDIEMS: Meridiem[] = ['AM', 'PM'];

const HOURS = Array.from({ length: 12 }, (_, index) => String(index + 1));

/**
 * Five-minute steps: twelve options fit in a dropdown without scrolling, and
 * no ferry has ever sailed at 07:43.
 */
const MINUTES = Array.from({ length: 12 }, (_, index) => String(index * 5).padStart(2, '0'));

const selectClasses =
    'rounded-base border-2 border-border bg-secondary-background px-2 py-2 text-sm font-base ' +
    'shadow-shadow focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring ' +
    'focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';

const toggleClasses =
    'rounded-base border-2 border-border px-2.5 py-1.5 text-xs font-heading transition-all ' +
    'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring ' +
    'focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';

/**
 * A 12-hour time control.
 *
 * `<input type="time">` renders 24-hour or 12-hour purely on the browser's
 * locale, with no way to force it, so the field is built from parts instead.
 * The value in and out stays 24-hour "HH:MM" for the API.
 */
export default function TimeField({
    id,
    value,
    disabled,
    onChange,
}: {
    id: string;
    value: string;
    disabled?: boolean;
    onChange: (value: string) => void;
}) {
    const { hour, minute, meridiem } = to12Hour(value || '07:45');

    // Keep an existing off-grid minute selectable rather than silently
    // rounding someone's saved 07:43 to 07:45.
    const minuteOptions = MINUTES.includes(minute)
        ? MINUTES
        : [...MINUTES, minute].sort((a, b) => Number(a) - Number(b));

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <select
                id={id}
                aria-label="Hour"
                className={selectClasses}
                disabled={disabled}
                value={hour}
                onChange={(event) => onChange(from12Hour(event.target.value, minute, meridiem))}
            >
                {HOURS.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>

            <span className="font-heading">:</span>

            <select
                aria-label="Minute"
                className={selectClasses}
                disabled={disabled}
                value={minute}
                onChange={(event) => onChange(from12Hour(hour, event.target.value, meridiem))}
            >
                {minuteOptions.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>

            <div className="ml-1 flex gap-1">
                {MERIDIEMS.map((option) => (
                    <button
                        key={option}
                        type="button"
                        disabled={disabled}
                        aria-pressed={meridiem === option}
                        onClick={() => onChange(from12Hour(hour, minute, option))}
                        className={cn(
                            toggleClasses,
                            meridiem === option
                                ? 'translate-x-boxShadowX translate-y-boxShadowY bg-main shadow-none'
                                : 'bg-secondary-background shadow-shadow hover:translate-x-boxShadowX hover:translate-y-boxShadowY hover:shadow-none',
                        )}
                    >
                        {option}
                    </button>
                ))}
            </div>

            <button
                type="button"
                disabled={disabled}
                onClick={() => onChange(nowTime())}
                className={cn(
                    toggleClasses,
                    'ml-1 bg-secondary-background uppercase shadow-shadow',
                    'hover:translate-x-boxShadowX hover:translate-y-boxShadowY hover:shadow-none',
                )}
            >
                Now
            </button>
        </div>
    );
}
