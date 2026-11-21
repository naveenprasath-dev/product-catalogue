/** The signed-in admin as shared by HandleInertiaRequests (only these fields reach the browser). */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
};

export type Auth = {
    user: User | null;
};
