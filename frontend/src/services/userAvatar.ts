import { apiClient } from '@/services/api'
import { avatarApiPath } from '@/services/userAvatarRules'

/** Private Avatar reads must use authenticated client and must never expose the storage filename. */
export async function readUserAvatar(userId: string | number): Promise<Blob> {
  const response = await apiClient.get<Blob>(avatarApiPath(userId), {
    responseType: 'blob',
    headers: { Accept: 'image/jpeg, image/png, image/webp' },
  })
  return response.data
}

export async function uploadUserAvatar(userId: string | number, file: File): Promise<void> {
  const form = new FormData()
  // PHP 8.3 only populates $_FILES for multipart POST, not multipart PUT.
  // CI4 method spoofing keeps the canonical PUT route and its authorization checks.
  form.append('_method', 'PUT')
  form.append('image', file)
  // Axios sets the multipart boundary. Existing interceptors send rotated CSRF token.
  await apiClient.post(avatarApiPath(userId), form)
}

export async function deleteUserAvatar(userId: string | number): Promise<void> {
  await apiClient.delete(avatarApiPath(userId))
}
