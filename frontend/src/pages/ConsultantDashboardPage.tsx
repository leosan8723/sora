import { Skeleton } from '../components/ui/Skeleton'
import { StateMessage } from '../components/ui/StateMessage'
import { useAuth } from '../features/auth/useAuth'
import { AppointmentsManager } from '../features/consultant-dashboard/AppointmentsManager'
import { AvailabilityEditor } from '../features/consultant-dashboard/AvailabilityEditor'
import { ConsultantProfileForm } from '../features/consultant-dashboard/ConsultantProfileForm'
import { ConsultationTypesEditor } from '../features/consultant-dashboard/ConsultationTypesEditor'
import { SpecialtiesEditor } from '../features/consultant-dashboard/SpecialtiesEditor'
import type { OwnerProfile } from '../features/consultant-dashboard/types'
import { useOwnerProfile } from '../features/consultant-dashboard/useConsultantDashboard'
import { useSpecialties } from '../features/consultants/useConsultants'
import { useI18n } from '../features/i18n/useI18n'
import { ApiError, errorMessage } from '../lib/api'

function emptyProfile(userId: number): OwnerProfile {
  return {
    id: 0,
    user_id: userId,
    headline: null,
    bio: null,
    years_experience: null,
    is_verified: false,
    is_active: true,
    verified_at: null,
    specialties: [],
    consultation_types: [],
    availability_rules: [],
  }
}

export function ConsultantDashboardPage() {
  const { user } = useAuth()
  const { t } = useI18n()
  const profile = useOwnerProfile()
  const specialties = useSpecialties()

  if (profile.isPending) {
    return (
      <div className="flex flex-col gap-6" aria-hidden="true">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full rounded-card" />
        <Skeleton className="h-40 w-full rounded-card" />
      </div>
    )
  }

  const notProvisioned =
    profile.isError &&
    profile.error instanceof ApiError &&
    profile.error.status === 404

  if (profile.isError && !notProvisioned) {
    return (
      <StateMessage
        tone="error"
        title={t('dashboard.profileFailed')}
        description={errorMessage(profile.error, t('common.networkError'))}
      />
    )
  }

  const data = profile.data ?? emptyProfile(user?.id ?? 0)

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-3xl">{t('dashboard.title')}</h1>
        <p className="text-ink-70">
          {user ? t('dashboard.welcomeText', { name: user.name }) : t('dashboard.profileText')}
        </p>
      </header>

      {notProvisioned ? (
        <StateMessage
          title={t('dashboard.notProvisionedTitle')}
          description={t('dashboard.notProvisionedText')}
        />
      ) : null}

      <AppointmentsManager />

      <ConsultantProfileForm profile={data} />
      <SpecialtiesEditor
        all={specialties.data ?? []}
        selected={data.specialties.map((specialty) => specialty.id)}
      />
      <ConsultationTypesEditor />
      <AvailabilityEditor />
    </div>
  )
}
