<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Database, MessageSquare, Settings, Trash2 } from 'lucide-vue-next';
import AiAssistantSettingsController from '@/actions/App/Http/Controllers/Settings/AiAssistantSettingsController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { edit } from '@/routes/ai-settings';

type AuditEntry = {
    id: number;
    ai_conversation_id: number;
    tool_payload: { sql?: string; rows_returned?: number } | null;
    created_at: string;
    conversation: { id: number; title: string } | null;
};

type Conversation = {
    id: number;
    title: string;
    created_at: string;
    updated_at: string;
};

type Props = {
    preferences: { mode: string; max_rows: number };
    queryAuditLog: AuditEntry[];
    conversations: Conversation[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AI Assistant settings', href: edit() }],
    },
});

const deleteConversation = (id: number) => {
    router.delete(AiAssistantSettingsController.destroyConversation.url(id), {
        preserveScroll: true,
    });
};

const formatDate = (dateStr: string) =>
    new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head title="AI Assistant settings" />

    <h1 class="sr-only">AI Assistant settings</h1>

    <div class="flex flex-col space-y-10">
        <!-- Mode & Row Limit -->
        <section class="space-y-6">
            <Heading
                variant="small"
                title="AI Assistant preferences"
                description="Configure how the AI assistant behaves for your account"
            />

            <Form
                :action="AiAssistantSettingsController.update()"
                class="space-y-6"
                v-slot="{ processing }"
            >
                <div class="grid gap-3">
                    <Label>Default mode</Label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors"
                            :class="preferences.mode === 'standard' ? 'border-primary bg-primary/5' : 'hover:bg-muted/40'"
                        >
                            <input type="radio" name="mode" value="standard" :checked="preferences.mode === 'standard'" class="mt-0.5" />
                            <div>
                                <p class="font-medium text-sm">Standard mode</p>
                                <p class="text-xs text-muted-foreground mt-0.5">Fast responses using pre-built analytics tools. Best for common questions about blockers, risks, and project health.</p>
                            </div>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors"
                            :class="preferences.mode === 'full' ? 'border-primary bg-primary/5' : 'hover:bg-muted/40'"
                        >
                            <input type="radio" name="mode" value="full" :checked="preferences.mode === 'full'" class="mt-0.5" />
                            <div>
                                <p class="font-medium text-sm flex items-center gap-1.5">
                                    Full mode
                                    <Badge variant="secondary" class="text-[10px] px-1.5 py-0">Beta</Badge>
                                </p>
                                <p class="text-xs text-muted-foreground mt-0.5">Reads all database tables. Can answer any custom question — counts, filters, aggregations. Slightly slower (2–5s extra).</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid gap-2 max-w-xs">
                    <Label for="max_rows">Max rows per query (Full mode)</Label>
                    <Select name="max_rows" :default-value="String(preferences.max_rows)">
                        <SelectTrigger id="max_rows">
                            <SelectValue placeholder="Select limit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="100">100 rows</SelectItem>
                            <SelectItem value="500">500 rows (default)</SelectItem>
                            <SelectItem value="1000">1,000 rows</SelectItem>
                            <SelectItem value="5000">5,000 rows</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">Higher limits give more complete answers but may slow down the response.</p>
                </div>

                <Button type="submit" :disabled="processing">
                    <Settings class="size-4" />
                    Save preferences
                </Button>
            </Form>
        </section>

        <Separator />

        <!-- Query Audit Log -->
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Query audit log"
                description="SQL queries run by the AI assistant in Full mode"
            />

            <div v-if="queryAuditLog.length === 0" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                <Database class="mx-auto mb-2 size-5 opacity-40" />
                No full-mode queries yet
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="entry in queryAuditLog"
                    :key="entry.id"
                    class="rounded-md border bg-muted/20 p-3 text-xs space-y-1"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">{{ formatDate(entry.created_at) }}</span>
                        <span class="text-muted-foreground">{{ entry.tool_payload?.rows_returned ?? 0 }} rows</span>
                    </div>
                    <code class="block break-all font-mono text-[11px] text-foreground/80">{{ entry.tool_payload?.sql ?? '—' }}</code>
                </div>
            </div>
        </section>

        <Separator />

        <!-- Conversation History -->
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Conversation history"
                description="Your past AI assistant conversations"
            />

            <div v-if="conversations.length === 0" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                <MessageSquare class="mx-auto mb-2 size-5 opacity-40" />
                No conversations yet
            </div>

            <div v-else class="space-y-1">
                <div
                    v-for="conv in conversations"
                    :key="conv.id"
                    class="flex items-center justify-between gap-3 rounded-md px-3 py-2 hover:bg-muted/40 group"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ conv.title }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatDate(conv.updated_at) }}</p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0 opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive"
                        @click="deleteConversation(conv.id)"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>
