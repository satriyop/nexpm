import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('navigate', (event) => {
        const page = event.detail.page;
        const flash = page.props.flash as Record<string, unknown> | undefined;
        if (!flash) return;

        // Custom structured toast: { type: 'success'|'error'|'info', message: string }
        if (flash.toast) {
            const data = flash.toast as FlashToast;
            toast[data.type](data.message);
        }

        // Standard Laravel session flash keys
        if (typeof flash.success === 'string') toast.success(flash.success);
        if (typeof flash.error === 'string') toast.error(flash.error);
        if (typeof flash.warning === 'string') toast.warning(flash.warning);
        if (typeof flash.info === 'string') toast.info(flash.info);
    });
}
