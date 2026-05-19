import { useApi } from '~/composables/useApi'
import type { PublicProfileUser } from '~/composables/useUserProfile'

export interface TrackComment {
  id: number
  song_id: number
  user_uid: string
  text: string
  timestamp: number
  created_at: string | null
  updated_at: string | null
  user: PublicProfileUser | null
}

interface TrackCommentsResponse {
  song_id: number
  comments: TrackComment[]
}

export const useTrackCommentsApi = () => {
  const { apiFetch } = useApi()

  const getTrackComments = async (songId: number | string): Promise<TrackCommentsResponse> => {
    return await apiFetch<TrackCommentsResponse>(`/songs/${songId}/comments`)
  }

  const createTrackComment = async (
    songId: number | string,
    payload: {
      text: string
      timestamp: number
    },
  ): Promise<TrackComment> => {
    return await apiFetch<TrackComment>(`/songs/${songId}/comments`, {
      method: 'POST',
      body: payload,
    })
  }

  const updateTrackComment = async (
    songId: number | string,
    commentId: number | string,
    payload: {
      text: string
    },
  ): Promise<TrackComment> => {
    return await apiFetch<TrackComment>(`/songs/${songId}/comments/${commentId}`, {
      method: 'PATCH',
      body: payload,
    })
  }

  const deleteTrackComment = async (
    songId: number | string,
    commentId: number | string,
  ): Promise<{ comment_id: number; song_id: number }> => {
    return await apiFetch<{ comment_id: number; song_id: number }>(`/songs/${songId}/comments/${commentId}`, {
      method: 'DELETE',
    })
  }

  return {
    getTrackComments,
    createTrackComment,
    updateTrackComment,
    deleteTrackComment,
  }
}
