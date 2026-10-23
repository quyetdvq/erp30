import { apiClient } from '@/services/api'
import { avatarApiPath } from '@/services/userAvatarRules'

export async function uploadUserAvatar(userId: string | number, file: File): Promise<{ avatar_url?: string | null }> {
  const form = new FormData()
  // Native multipart POST populates PHP $_FILES without HTTP-method spoofing.
  form.append('image', file, file.name)
  // Axios sets the multipart boundary; the shared interceptor supplies CSRF.
  const response = await apiClient.post<{ data: { avatar_url?: string | null } }>(
    avatarApiPath(userId), form, { timeout: 30_000 },
  )
  return response.data.data
}

export async function deleteUserAvatar(userId: string | number): Promise<void> {
  await apiClient.delete(avatarApiPath(userId))
}
