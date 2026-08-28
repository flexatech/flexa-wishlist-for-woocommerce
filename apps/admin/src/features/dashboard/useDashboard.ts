import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

export interface DashboardTotals {
    totalItems: number;
    totalLists: number;
    activeWishlists: number;
    guestWishlists: number;
}

export interface TopProduct {
    productId: number;
    count: number;
    name: string;
}

export interface DashboardData {
    totals: DashboardTotals;
    topProducts: TopProduct[];
}

const DASHBOARD_KEY = ["dashboard"] as const;

export function useDashboard() {
    return useQuery<DashboardData>({
        queryKey: DASHBOARD_KEY,
        queryFn: () => api.get<DashboardData>("admin/dashboard"),
    });
}
