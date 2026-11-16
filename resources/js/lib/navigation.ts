import { BookOpen, Images, Sparkles, Wallet } from '@lucide/vue';
import { index as credits } from '@/routes/credits';
import { create, index } from '@/routes/generations';
import type { NavItem } from '@/types';

export const homeUrl = create;

export const mainNavItems: NavItem[] = [
    { title: 'Generate', href: create(), icon: Sparkles },
    { title: 'History', href: index(), icon: Images },
    { title: 'Credits', href: credits(), icon: Wallet },
];

export const footerNavItems: NavItem[] = [
    {
        title: 'Pixelforge API',
        href: 'https://pixelforge.promptedit.dev/docs',
        icon: BookOpen,
    },
];
