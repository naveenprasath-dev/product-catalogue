// Props from the Admin\* controllers (shapes of App\Http\Resources\Admin\*).

export type EnquiryStatus = 'pending' | 'sent' | 'failed';

export type DashboardStats = {
    products: number;
    categories: number;
    enquiries: Record<EnquiryStatus, number>;
    last_import_at: string | null;
};

export type Enquiry = {
    id: number;
    product_id: number;
    product_title: string;
    name: string;
    email: string;
    phone: string | null;
    message: string | null;
    status: EnquiryStatus;
    customer_account: boolean;
    created_at: string;
    mailed_at: string | null;
    resendable: boolean;
};

/** A Laravel paginated resource collection ({ data, links, meta }). */
export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

/** App\Http\Resources\Admin\CurrencyResource. `rate` = units per 1 unit of the base currency (USD). */
export type AdminCurrency = {
    code: string;
    name: string;
    symbol: string;
    rate: number;
    enabled: boolean;
    is_default: boolean;
    is_base: boolean;
    rate_updated_at: string | null;
};
