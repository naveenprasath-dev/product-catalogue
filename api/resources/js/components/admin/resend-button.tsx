import { Form } from '@inertiajs/react';
import { RotateCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { resend } from '@/routes/admin/enquiries';

/** POSTs the re-send; the server redirects back and the result arrives as a flash toast. */
export function ResendButton({
    enquiryId,
    size = 'default',
}: {
    enquiryId: number;
    size?: 'default' | 'sm';
}) {
    return (
        <Form {...resend.form(enquiryId)} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    size={size}
                    variant="outline"
                    disabled={processing}
                    data-test="resend-button"
                >
                    {processing ? <Spinner /> : <RotateCw />}
                    Re-send
                </Button>
            )}
        </Form>
    );
}
