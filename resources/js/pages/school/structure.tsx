import { Head } from '@inertiajs/react';
import AcademicYearsCard from '@/components/school/academic-years-card';
import BulkClassesCard from '@/components/school/bulk-classes-card';
import CampusesCard from '@/components/school/campuses-card';
import ClassesSubjectsCard from '@/components/school/classes-subjects-card';
import SectionsCard from '@/components/school/sections-card';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/school/structure';
import type {
    AcademicYear,
    Campus,
    ClassLadderStep,
    PaginatedSections,
    SchoolClass,
    StaffOption,
    StructureFilters,
    Subject,
} from '@/types';

type Props = {
    academicYears: AcademicYear[];
    campuses: Campus[];
    classes: SchoolClass[];
    classLadder: ClassLadderStep[];
    subjects: Subject[];
    sections: PaginatedSections;
    staffOptions: StaffOption[];
    filters: StructureFilters;
};

export default function Structure({
    academicYears,
    campuses,
    classes,
    classLadder,
    subjects,
    sections,
    staffOptions,
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
                {canManage && classLadder.length > 0 && (
                    <BulkClassesCard
                        ladder={classLadder}
                        classes={classes}
                        years={academicYears}
                        campuses={campuses}
                    />
                )}
                <SectionsCard
                    sections={sections}
                    years={academicYears}
                    campuses={campuses}
                    classes={classes}
                    filters={filters}
                    staffOptions={staffOptions}
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
