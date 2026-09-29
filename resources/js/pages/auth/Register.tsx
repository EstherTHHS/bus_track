import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <GuestLayout title="Register" description="Start counting your ferry days.">
            <Head title="Register" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post('/register');
                }}
                className="grid gap-4"
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        autoComplete="name"
                        autoFocus
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                    />
                    {errors.name && <p className="text-sm font-heading text-danger-foreground">{errors.name}</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="username"
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
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    {errors.password && <p className="text-sm font-heading text-danger-foreground">{errors.password}</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                    />
                </div>

                <Button type="submit" disabled={processing}>
                    Register
                </Button>

                <p className="text-center text-sm">
                    Already have an account?{' '}
                    <Link href="/login" className="font-heading underline">
                        Log in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
