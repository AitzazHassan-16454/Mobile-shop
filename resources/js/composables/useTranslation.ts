import { computed, ref } from 'vue';

export type Language = 'en' | 'ur';

const currentLanguage = ref<Language>(
    (typeof localStorage !== 'undefined' &&
        (localStorage.getItem('app_lang') as Language)) ||
        'en',
);

export function useTranslation() {
    const setLanguage = (lang: Language) => {
        currentLanguage.value = lang;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('app_lang', lang);
        }
    };

    const isUrdu = computed(() => currentLanguage.value === 'ur');

    const t = (key: string): string => {
        return translations[currentLanguage.value]?.[key] || key;
    };

    return {
        currentLanguage,
        setLanguage,
        isUrdu,
        t,
    };
}

export const translations: Record<Language, Record<string, string>> = {
    en: {
        Dashboard: 'Dashboard',
        Customers: 'Customers & Khata',
        'Yearly Dues': 'Yearly Dues',
        'Sales History & Direct Sale': 'Sales History',
        'Sale Returns': 'Sale Returns',
        'All Payments': 'Payments Desk',
        Expenses: 'Expenses',
        'Mobile Repairs': 'Mobile Repairs',
        'Products & Stock': 'Products & Stock',
        Categories: 'Categories',
        Units: 'Units',
        'Stock Adjustments': 'Stock Adjustments',
        'Stock Transfers': 'Stock Transfers',
        Discounts: 'Discounts & Offers',
        Suppliers: 'Suppliers',
        'Analytics & Reports': 'Reports & Analytics',
        POS: 'POS',
        Admin: 'Admin',
    },
    ur: {
        Dashboard: 'ڈیش بورڈ',
        Customers: 'گاہک اور کھاتہ',
        'Yearly Dues': 'سالانہ بقایا جات',
        'Sales History & Direct Sale': 'سیل ہسٹری',
        'Sale Returns': 'سیل واپسی',
        'All Payments': 'ادائیگیاں ڈیسک',
        Expenses: 'اخراجات',
        'Mobile Repairs': 'موبائل مرمت',
        'Products & Stock': 'سامان اور اسٹاک',
        Categories: 'کیٹیگریز',
        Units: 'پیمائش کے یونٹ',
        'Stock Adjustments': 'اسٹاک تبدیلی',
        'Stock Transfers': 'اسٹاک ٹرانسفر',
        Discounts: 'رعایت اور آفرز',
        Suppliers: 'سپلائرز',
        'Analytics & Reports': 'رپورٹس اور تجزئیے',
        POS: 'پی او ایس',
        Admin: 'ایڈمن',
    },
};
