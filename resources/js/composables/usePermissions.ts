import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();
    const role = computed(() => (page.props.auth?.user as any)?.role as string | undefined);
    const canEdit = computed(() => role.value !== 'project_manager');
    return { canEdit, role };
}
