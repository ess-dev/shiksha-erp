import { Head } from '@inertiajs/react';
import AcademicYearsCard from '@/components/school/academic-years-card';
import CampusesCard from '@/components/school/campuses-card';
import ClassesSubjectsCard from '@/components/school/classes-subjects-card';
import SectionsCard from '@/components/school/sections-card';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/school/structure';
import type {
    AcademicYear,
    Campus,
    PaginatedSections,
    SchoolClass,
    StructureFilters,
    Subject,
} from '@/types';

type Props = {
    academicYears: AcademicYear[];
    campuses: Campus[];
    classes: SchoolClass[];
    subjects: Subject[];
    sections: PaginatedSections;
    filters: StructureFilters;
};

export default function Structure({
    academicYears,
    campuses,
    classes,
    subjects,
    sections,
    filters,
}: Props) {
    const canManage = useCan('structure.manage');

    return (
        <>
            <Head title="School structure" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    School structure
                </h1>

                <AcademicYearsCard
                    years={academicYears}
                    canManage={canManage}
                />
                <CampusesCard campuses={campuses} canManage={canManage} />
                <ClassesSubjectsCard
                    classes={classes}
                    subjects={subjects}
                    canManage={canManage}
                />
                <SectionsCard
                    sections={sections}
                    years={academicYears}
                    campuses={campuses}
                    classes={classes}
                    filters={filters}
                    canManage={canManage}
                />
            </div>
        </>
    );
}

Structure.layout = {
    breadcrumbs: [
        {
            title: 'School structure',
            href: index(),
        },
    ],
};
