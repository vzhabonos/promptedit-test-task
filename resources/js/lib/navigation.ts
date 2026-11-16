import { BookOpen, ChartColumn, Images, Sparkles, Wallet } from '@lucide/vue';
import { report } from '@/routes/admin';
import { index as credits } from '@/routes/credits';
import { create, index } from '@/routes/generations';
import type { NavItem } from '@/types';

export const homeUrl = create;

export const mainNavItems: NavItem[] = [
    { title: 'Generate', href: create(), icon: Sparkles },
    { title: 'History', href: index(), icon: Images },
    { title: 'Credits', href: credits(), icon: Wallet },
];

/** Only shown to operators (users with is_admin). */
export const adminNavItems: NavItem[] = [
    { title: 'Spend report', href: report(), icon: ChartColumn },
];

export const footerNavItems: NavItem[] = [
    {
        title: 'Pixelforge API',
        href: 'https://pixelforge.promptedit.dev/docs',
        icon: BookOpen,
    },
];
