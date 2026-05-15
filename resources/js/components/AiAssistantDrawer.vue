<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { AlertCircle, Bot, Loader2, RotateCcw, Send, Settings, Sparkles } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

import { store as storeAiMessage } from '@/actions/App/Http/Controllers/Admin/AiAssistantController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { edit as editAiSettings } from '@/routes/ai-settings';

type ChatMessage = {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
    tool_name?: string | null;
    tool_payload?: {
        sources?: string[];
        follow_up_suggestions?: string[];
        record_links?: { label: string; url: string }[];
    };
};

const page = usePage();
const open = ref(false);
const input = ref('');
const processing = ref(false);
const streaming = ref(false);
const error = ref<string | null>(null);
const conversationId = ref<number | null>(null);
const messages = ref<ChatMessage[]>([]);
const messagesEl = ref<HTMLElement | null>(null);

const isSuperAdmin = computed(() => page.props.auth.user.role === 'super_admin');
const aiMode = computed<string>(() => {
    const prefs = (page.props.auth.user as Record<string, unknown>).ai_preferences as Record<string, unknown> | null;

    return (prefs?.mode as string) ?? 'standard';
});

const quickPrompts = [
    'Briefing proyek hari ini',
    'Apa risiko terbesar saat ini?',
    'Assignment mana yang telat atau stuck?',
    'Cek gap workflow',
    'Apa yang siap dibuat laporan?',
    'Apa prioritas tindakan saya hari ini?',
];

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const toolLabels: Record<string, string> = {
    check_report_readiness: 'Kesiapan laporan',
    find_blocked_assignments: 'Assignment telat',
    general_help: 'Bantuan',
    list_users: 'Daftar user',
    query_database: 'Query database',
    resolve_entity_context: 'Pencarian konteks',
    workflow_knowledge: 'Pengetahuan workflow',
    contextual_page_summary: 'Ringkasan halaman',
    detect_workflow_gaps: 'Gap workflow',
    project_health_briefing: 'Briefing proyek',
    summarize_priority_actions: 'Prioritas tindakan',
    summarize_project_risks: 'Risiko proyek',
    summarize_subcontractor_blockers: 'Blocker subcon',
    summarize_dashboard: 'Ringkasan dashboard',
};

const scrollToBottom = async () => {
    await nextTick();

    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
};

const numericId = (value: unknown): number | undefined => (typeof value === 'number' ? value : undefined);

const currentContext = () => {
    const props = page.props as Record<string, unknown>;
    const assignment = props.assignment as Record<string, unknown> | undefined;
    const site = (props.site ?? assignment?.site) as Record<string, unknown> | undefined;
    const project = (props.project ?? site?.project) as Record<string, unknown> | undefined;
    const url = page.url;

    let type = 'page';

    if (numericId(assignment?.id)) {
        type = 'assignment';
    } else if (numericId(site?.id)) {
        type = 'site';
    } else if (numericId(project?.id)) {
        type = 'project';
    } else if (url.includes('/reports')) {
        type = 'reports';
    } else if (url.includes('/dashboard')) {
        type = 'dashboard';
    }

    return {
        type,
        id: numericId(assignment?.id) ?? numericId(site?.id) ?? numericId(project?.id),
        assignment_id: numericId(assignment?.id),
        site_id: numericId(site?.id),
        project_id: numericId(project?.id),
        label: String(assignment?.site_code ?? site?.site_code ?? project?.name ?? url),
        url,
        component: page.component,
    };
};

const ask = async (prompt?: string) => {
    const message = (prompt ?? input.value).trim();

    if (!message || processing.value || streaming.value) {
        return;
    }

    input.value = '';
    error.value = null;
    processing.value = true;
    messages.value.push({ role: 'user', content: message });
    await scrollToBottom();

    try {
        const response = await fetch(storeAiMessage.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                message,
                conversation_id: conversationId.value,
                mode: aiMode.value,
                context: { ...currentContext() },
            }),
        });

        if (!response.ok || !response.body) {
            const payload = await response.json().catch(() => ({}));

            throw new Error(typeof payload.message === 'string' ? payload.message : 'AI assistant request failed.');
        }

        // Push placeholder that gets filled as tokens arrive
        const placeholder: ChatMessage = { role: 'assistant', content: '', tool_name: null };
        messages.value.push(placeholder);
        const msgIndex = messages.value.length - 1;

        streaming.value = true;
        processing.value = false;

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();

            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            // SSE events are separated by double newlines
            const blocks = buffer.split('\n\n');
            buffer = blocks.pop() ?? '';

            for (const block of blocks) {
                let eventName = '';
                let eventData = '';

                for (const line of block.split('\n')) {
                    if (line.startsWith('event: ')) {
                        eventName = line.slice(7).trim();
                    } else if (line.startsWith('data: ')) {
                        // SSE spec: multiple data lines must be concatenated with \n
                        eventData = eventData ? eventData + '\n' + line.slice(6) : line.slice(6);
                    }
                }

                if (!eventName || !eventData) {
                    continue;
                }

                const data = JSON.parse(eventData) as Record<string, unknown>;

                if (eventName === 'tool_data') {
                    messages.value[msgIndex].tool_name = data.tool_name as string | null;
                    messages.value[msgIndex].tool_payload = data.tool_payload as ChatMessage['tool_payload'];
                } else if (eventName === 'text') {
                    messages.value[msgIndex].content += data.delta as string;
                    await scrollToBottom();
                } else if (eventName === 'done') {
                    conversationId.value = (data.conversation_id as number) ?? conversationId.value;
                    messages.value[msgIndex].id = data.message_id as number;
                } else if (eventName === 'error') {
                    throw new Error((data.message as string) ?? 'AI assistant request failed.');
                }
            }
        }
    } catch (requestError) {
        error.value = requestError instanceof Error ? requestError.message : 'AI assistant request failed.';
    } finally {
        processing.value = false;
        streaming.value = false;
        await scrollToBottom();
    }
};

const resetConversation = () => {
    conversationId.value = null;
    messages.value = [];
    input.value = '';
    error.value = null;
};
</script>

<template>
    <Sheet v-if="isSuperAdmin" v-model:open="open">
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Open AI assistant">
                            <Sparkles class="size-4" />
                        </Button>
                    </SheetTrigger>
                </TooltipTrigger>
                <TooltipContent side="bottom">AI Assistant</TooltipContent>
            </Tooltip>
        </TooltipProvider>

        <SheetContent side="right" class="w-full gap-0 p-0 sm:max-w-[520px]">
            <SheetHeader class="border-b px-5 py-4 pr-12">
                <div class="flex items-center justify-between gap-3">
                    <SheetTitle class="flex items-center gap-2 text-base">
                        <Bot class="size-4" />
                        AI Assistant
                        <Badge
                            :variant="aiMode === 'full' ? 'default' : 'secondary'"
                            class="text-[10px] px-1.5 py-0 font-normal"
                        >
                            {{ aiMode === 'full' ? 'Full DB' : 'Standard' }}
                        </Badge>
                    </SheetTitle>
                    <div class="flex items-center gap-1">
                        <Button
                            v-if="messages.length > 0"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8"
                            :disabled="processing || streaming"
                            @click="resetConversation"
                        >
                            <RotateCcw class="size-4" />
                            Pertanyaan baru
                        </Button>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="size-8" as-child>
                                    <Link :href="editAiSettings()">
                                        <Settings class="size-3.5" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">AI settings</TooltipContent>
                        </Tooltip>
                    </div>
                </div>
            </SheetHeader>

            <div ref="messagesEl" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <div v-if="messages.length === 0" class="grid gap-2">
                    <button
                        v-for="prompt in quickPrompts"
                        :key="prompt"
                        type="button"
                        class="rounded-md border border-border px-3 py-2 text-left text-sm transition hover:bg-accent hover:text-accent-foreground"
                        @click="ask(prompt)"
                    >
                        {{ prompt }}
                    </button>
                </div>

                <div
                    v-for="(message, index) in messages"
                    :key="message.id ?? `${message.role}-${index}`"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[86%] rounded-md px-3 py-2 text-sm leading-6 whitespace-pre-wrap"
                        :class="
                            message.role === 'user'
                                ? 'bg-primary text-primary-foreground'
                                : 'border border-border bg-muted/40 text-foreground'
                        "
                    >
                        <div v-if="message.tool_name" class="mb-1 text-xs font-medium text-muted-foreground">
                            {{ toolLabels[message.tool_name] ?? message.tool_name.replaceAll('_', ' ') }}
                        </div>
                        {{ message.content
                        }}<span
                            v-if="streaming && index === messages.length - 1"
                            class="ml-0.5 inline-block h-3.5 w-0.5 animate-pulse bg-current align-middle"
                        />
                        <div
                            v-if="message.role === 'assistant' && message.tool_payload?.sources?.length"
                            class="mt-2 flex flex-wrap gap-1"
                        >
                            <Badge
                                v-for="source in message.tool_payload.sources"
                                :key="source"
                                variant="secondary"
                                class="h-5 rounded px-1.5 text-[10px] font-normal"
                            >
                                {{ source }}
                            </Badge>
                        </div>
                        <div
                            v-if="message.role === 'assistant' && message.tool_payload?.record_links?.length"
                            class="mt-2 flex flex-wrap gap-1"
                        >
                            <Link
                                v-for="link in message.tool_payload.record_links"
                                :key="link.url"
                                :href="link.url"
                                class="rounded border border-border bg-background px-2 py-1 text-xs text-foreground transition hover:bg-accent"
                            >
                                {{ link.label }}
                            </Link>
                        </div>
                        <div
                            v-if="message.role === 'assistant' && message.tool_payload?.follow_up_suggestions?.length"
                            class="mt-2 flex flex-wrap gap-1"
                        >
                            <button
                                v-for="suggestion in message.tool_payload.follow_up_suggestions"
                                :key="suggestion"
                                type="button"
                                class="rounded border border-border bg-background px-2 py-1 text-left text-xs text-foreground transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="processing || streaming"
                                @click="ask(suggestion)"
                            >
                                {{ suggestion }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="processing" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 class="size-4 animate-spin" />
                    Memproses
                </div>

                <div v-if="error" class="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ error }}</span>
                </div>
            </div>

            <form class="flex gap-2 border-t p-4" @submit.prevent="ask()">
                <textarea
                    v-model="input"
                    rows="2"
                    class="min-h-11 flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="processing || streaming"
                    placeholder="Tanyakan risiko proyek, blocker, laporan, atau prioritas"
                    @keydown.enter.exact.prevent="ask()"
                />
                <Button type="submit" size="icon" :disabled="processing || streaming || input.trim() === ''" aria-label="Send message">
                    <Loader2 v-if="processing || streaming" class="size-4 animate-spin" />
                    <Send v-else class="size-4" />
                </Button>
            </form>
        </SheetContent>
    </Sheet>
</template>
