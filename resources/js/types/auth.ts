/** The account signed in with the password: the user row as the server shares it. */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/** The account of a phone linked with a code: only the name and the photo are shared with it. */
export type LinkedPhoneUser = {
    name: string;
    /** The address of the photo of the person, null without one. */
    photo: string | null;
};

/** Who is signed in: the full account, the account of a linked phone, or nobody (a guest). */
export type Auth = {
    user: User | LinkedPhoneUser | null;
};
