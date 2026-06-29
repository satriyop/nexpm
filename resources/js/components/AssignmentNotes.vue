<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { MessageSquare, PencilLine, Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AssignmentComment } from '@/types';

const props = defineProps<{
    comments: AssignmentComment[];
    storeUrl?: string | null;
    compact?: boolean;
    maxComments?: number;
    title?: string;
    description?: string;
}>();

const form = useForm({
    body: '',
});

const showAllComments = ref(false);

const compactLimit = computed(() => props.maxComments ?? 5);

const visibleComments = computed(() => {
    if (!props.compact) {
        return props.comments;
    }

    if (showAllComments.value) {
        return props.comments;
    }

    return props.comments.slice(0, compactLimit.value);
});

const canToggleComments = computed(
    () => props.compact && props.comments.length > compactLimit.value,
);

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function submit(): void {
    if (!props.storeUrl) {
        return;
    }

    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>

<template>
    <Card class="overflow-hidden border-sidebar-border/70 shadow-sm">
        <CardHeader class="border-b bg-muted/30" :class="compact ? 'p-4' : ''">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 rounded-md bg-background p-2 shadow-xs">
                        <MessageSquare class="size-4 text-primary" />
                    </div>
                    <div>
                        <CardTitle>{{ title ?? 'Notes' }}</CardTitle>
                        <CardDescription>
                            {{
                                description ??
                                'Latest field updates and project comments.'
                            }}
                        </CardDescription>
                    </div>
                </div>
                <div
                    class="rounded-full bg-background px-2.5 py-1 text-xs text-muted-foreground shadow-xs"
                >
                    {{ comments.length }}
                    {{ comments.length === 1 ? 'note' : 'notes' }}
                </div>
            </div>
        </CardHeader>
        <CardContent
            class="space-y-6 p-4 sm:p-5"
            :class="compact ? 'sm:p-4' : ''"
        >
            <form
                v-if="storeUrl && compact"
                class="rounded-md border bg-muted/20 p-3 sm:p-4"
                @submit.prevent="submit"
            >
                <div class="mb-3 flex items-center gap-2 text-sm font-medium">
                    <PencilLine class="size-4 text-muted-foreground" />
                    Add a note
                </div>
                <div class="space-y-2">
                    <textarea
                        v-model="form.body"
                        name="body"
                        rows="3"
                        class="flex min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Write the latest field update, blocker, client feedback, or follow-up action..."
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.body" />
                </div>

                <Button
                    type="submit"
                    class="mt-3 w-full"
                    :disabled="form.processing"
                >
                    <Send class="mr-2 size-4" />
                    {{ form.processing ? 'Adding...' : 'Add Note' }}
                </Button>
            </form>

            <div
                v-if="comments.length === 0"
                class="rounded-md border border-dashed bg-muted/20 px-4 py-8 text-center text-sm text-muted-foreground"
            >
                No notes yet.
            </div>

            <div
                v-else
                class="space-y-3"
                :class="
                    compact ? 'lg:max-h-[22rem] lg:overflow-y-auto lg:pr-1' : ''
                "
            >
                <div
                    v-if="compact"
                    class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground"
                >
                    <span class="font-medium text-foreground/80">
                        {{
                            showAllComments
                                ? 'All notes'
                                : `Latest ${compactLimit} notes`
                        }}
                    </span>
                    <div class="flex items-center gap-2">
                        <span v-if="comments.length > visibleComments.length">
                            Showing latest {{ visibleComments.length }} of
                            {{ comments.length }}
                        </span>
                        <span v-else>Showing {{ comments.length }}</span>
                        <Button
                            v-if="canToggleComments"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            @click="showAllComments = !showAllComments"
                        >
                            {{
                                showAllComments
                                    ? `Show latest ${compactLimit}`
                                    : 'Show all'
                            }}
                        </Button>
                    </div>
                </div>

                <div
                    class="relative space-y-4 pl-4 before:absolute before:top-2 before:bottom-2 before:left-1 before:w-px before:bg-border"
                >
                    <article
                        v-for="comment in visibleComments"
                        :key="comment.id"
                        class="relative rounded-md border bg-background shadow-xs"
                        :class="compact ? 'p-3' : 'p-4'"
                    >
                        <span
                            class="absolute top-5 -left-[1.18rem] size-2.5 rounded-full border-2 border-background bg-primary"
                        />
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ comment.user?.name ?? 'Deleted user' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        comment.user?.role
                                            ? String(comment.user.role).replace(
                                                  '_',
                                                  ' ',
                                              )
                                            : 'User'
                                    }}
                                </p>
                            </div>
                            <time class="text-xs text-muted-foreground">
                                {{ formatDateTime(comment.created_at) }}
                            </time>
                        </div>
                        <p
                            class="mt-3 text-sm leading-relaxed break-words whitespace-pre-wrap"
                        >
                            {{ comment.body }}
                        </p>
                    </article>
                    <p
                        v-if="
                            compact &&
                            comments.length > visibleComments.length &&
                            !showAllComments
                        "
                        class="pl-1 text-xs text-muted-foreground"
                    >
                        Only the latest {{ visibleComments.length }} notes are
                        displayed here.
                        {{ comments.length - visibleComments.length }} older
                        {{
                            comments.length - visibleComments.length === 1
                                ? 'note'
                                : 'notes'
                        }}
                        hidden.
                    </p>
                </div>
            </div>

            <form
                v-if="storeUrl && !compact"
                class="rounded-md border bg-muted/20 p-3 sm:p-4"
                @submit.prevent="submit"
            >
                <div class="mb-3 flex items-center gap-2 text-sm font-medium">
                    <PencilLine class="size-4 text-muted-foreground" />
                    Add a note
                </div>
                <div class="space-y-2">
                    <textarea
                        v-model="form.body"
                        name="body"
                        :rows="compact ? 3 : 4"
                        class="flex min-h-28 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Write the latest field update, blocker, client feedback, or follow-up action..."
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.body" />
                </div>

                <div class="mt-3 flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <Send class="mr-2 size-4" />
                        {{ form.processing ? 'Adding...' : 'Add Note' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
