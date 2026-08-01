import { useTranslation } from 'react-i18next';

export function DashboardHome() {
  const { t } = useTranslation();

  return (
    <div className="p-6">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('nav.dashboard')}</h1>
      <p className="mt-2 text-neutral-500">
        Shop owner dashboard — populated starting Phase 2 (auth) and Phase 5 (catalog).
      </p>
    </div>
  );
}
