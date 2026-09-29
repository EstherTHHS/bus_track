import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    return (
        <GuestLayout title="Log in" description="Pick up where you left off.">
            <Head title="Log in" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post('/login');
                }}
                className="grid gap-4"
            >
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="username"
                        autoFocus
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email && <p className="text-sm font-heading text-danger-foreground">{errors.email}</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    {errors.password && <p className="text-sm font-heading text-danger-foreground">{errors.password}</p>}
                </div>

                <Button type="submit" disabled={processing}>
                    Log in
                </Button>

                <p className="text-center text-sm">
                    No account yet?{' '}
                    <Link href="/register" className="font-heading underline">
                        Register
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
